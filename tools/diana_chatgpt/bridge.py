"""Private ChatGPT-to-CRM transport. No microphone, public listener, or SQL access to CRM."""
from __future__ import annotations

import contextlib
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import sqlite3
import stat
import threading
import time
import unicodedata
import urllib.error
import urllib.request
from zoneinfo import ZoneInfo

URLS = {
    "roster": "https://muj-obchod.jirijanousek.cz/diana-roster.php",
    "note": "https://muj-obchod.jirijanousek.cz/api.php?action=diana_note",
}
OPERATION = re.compile(r"[A-Za-z0-9_-]{8,64}\Z")
REFERENCE = re.compile(r"[A-Za-z0-9_-]{32}\Z")


class BridgeError(Exception):
    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code


def canonical(value) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def digest(value) -> str:
    return hashlib.sha256(canonical(value).encode()).hexdigest()


def clean_name(value: str) -> str:
    if not isinstance(value, str) or any(ord(c) < 32 for c in value):
        raise BridgeError("invalid_name", "Zadej celé jméno klienta.")
    value = unicodedata.normalize("NFC", " ".join(value.split()))
    if len(value.split()) < 2 or len(value.encode()) > 160:
        raise BridgeError("invalid_name", "Zadej celé jméno do 160 bajtů.")
    return value


def private_fd(path: Path, flags: int) -> int:
    try:
        fd = os.open(path, flags | os.O_NOFOLLOW, 0o600)
    except OSError:
        raise BridgeError("private_file", "Soukromý soubor není dostupný.") from None
    info = os.fstat(fd)
    if not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o077:
        os.close(fd)
        raise BridgeError("private_file", "Soukromý soubor musí patřit uživateli a mít práva 600.")
    return fd


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class CRMTransport:
    """Only these two fixed HTTPS endpoints; credentials are never returned or logged."""

    def __init__(self, token_file: Path):
        self.token_file = token_file
        self.opener = urllib.request.build_opener(NoRedirect())

    def post(self, endpoint: str, payload: dict) -> dict:
        fd = private_fd(self.token_file, os.O_RDONLY)
        with os.fdopen(fd, "rb") as stream:
            raw = stream.read(128)
        try:
            token = raw.decode("ascii").strip()
        except UnicodeError:
            token = ""
        if not re.fullmatch(r"[a-f0-9]{64}", token):
            raise BridgeError("token_invalid", "Token Mého obchodu nemá správný formát.")
        request = urllib.request.Request(
            URLS[endpoint], data=canonical(payload).encode(), method="POST",
            headers={"Authorization": "Bearer " + token, "Content-Type": "application/json",
                     "Cache-Control": "no-store", "Accept": "application/json"},
        )
        try:
            with self.opener.open(request, timeout=12) as response:
                if response.status != 200:
                    raise BridgeError("crm_http", "CRM nepotvrdilo požadavek.")
                body = response.read(65537)
        except urllib.error.HTTPError as error:
            raise BridgeError("crm_http_" + str(error.code), "CRM vrátilo HTTP " + str(error.code) + ".") from None
        except (OSError, TimeoutError):
            raise BridgeError("crm_unreachable", "CRM neodpovědělo. Nepoužívej nové ID zápisu.") from None
        try:
            if len(body) > 65536:
                raise ValueError
            result = json.loads(body)
            if not isinstance(result, dict) or result.get("ok") is not True:
                raise ValueError
            return result
        except (ValueError, UnicodeError):
            raise BridgeError("crm_response", "CRM neposlalo platné potvrzení.") from None


def validate_contact(row: dict) -> dict:
    if not isinstance(row, dict) or type(row.get("id")) is not int or row["id"] < 1:
        raise BridgeError("crm_response", "CRM vrátilo neplatné ID klienta.")
    result = {"id": row["id"]}
    for key in ("first_name", "last_name", "email", "phone"):
        value = row.get(key)
        if not isinstance(value, str) or len(value) > 500 or any(ord(c) < 32 for c in value):
            raise BridgeError("crm_response", "CRM vrátilo neplatnou kartu klienta.")
        result[key] = value
    clean_name(result["first_name"] + " " + result["last_name"])
    return result


class Bridge:
    def __init__(self, state_dir: Path, transport, clock=time.time):
        self.state_dir = Path(state_dir)
        self.transport = transport
        self.clock = clock
        self.mutex = threading.RLock()
        self.state_dir.mkdir(parents=True, mode=0o700, exist_ok=True)
        info = self.state_dir.lstat()
        if not stat.S_ISDIR(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o077:
            raise BridgeError("state_permissions", "Adresář stavu musí být soukromý a mít práva 700.")
        self.db_path = self.state_dir / "events.sqlite3"
        os.close(private_fd(self.db_path, os.O_RDWR | os.O_CREAT))
        with self._locked(), self._db() as db:
            db.executescript("""
                CREATE TABLE IF NOT EXISTS refs(
                    ref TEXT PRIMARY KEY, contact TEXT NOT NULL, expires INTEGER NOT NULL);
                CREATE TABLE IF NOT EXISTS events(
                    hash TEXT PRIMARY KEY, request_key TEXT NOT NULL UNIQUE,
                    contact TEXT NOT NULL, payload TEXT NOT NULL,
                    state TEXT NOT NULL, activity_id INTEGER, last_error TEXT,
                    created INTEGER NOT NULL, updated INTEGER NOT NULL);
                CREATE TABLE IF NOT EXISTS aliases(
                    operation_id TEXT PRIMARY KEY, event_hash TEXT NOT NULL REFERENCES events(hash));
            """)

    @contextlib.contextmanager
    def _db(self):
        db = sqlite3.connect(self.db_path, timeout=15)
        db.row_factory = sqlite3.Row
        db.execute("PRAGMA foreign_keys=ON")
        try:
            yield db
            db.commit()
        except BaseException:
            db.rollback()
            raise
        finally:
            db.close()

    @contextlib.contextmanager
    def _locked(self):
        with self.mutex:
            fd = private_fd(self.state_dir / "delivery.lock", os.O_RDWR | os.O_CREAT)
            try:
                fcntl.flock(fd, fcntl.LOCK_EX)
                yield
            finally:
                fcntl.flock(fd, fcntl.LOCK_UN)
                os.close(fd)

    def context(self) -> dict:
        now = dt.datetime.fromtimestamp(self.clock(), ZoneInfo("Europe/Prague"))
        return {"timezone": "Europe/Prague", "today": now.date().isoformat(),
                "now": now.isoformat(), "target": "Můj obchod", "writes": ["note"],
                "calendar_scheduling": False,
                "kmen_delivery": "Samostatná synchronizace; tento nástroj její výsledek nepotvrzuje."}

    def _lookup(self, name: str) -> list[dict]:
        data = self.transport.post("roster", {"name": name})
        rows = data.get("matches")
        if not isinstance(rows, list) or len(rows) > 20:
            raise BridgeError("crm_response", "CRM vrátilo neplatný seznam klientů.")
        rows = [validate_contact(row) for row in rows]
        if len({row["id"] for row in rows}) != len(rows):
            raise BridgeError("crm_response", "CRM vrátilo opakovaná ID klientů.")
        return [r for r in rows if clean_name(r["first_name"] + " " + r["last_name"]).casefold() == name.casefold()]

    def find_client(self, name: str, contact_id: int | None = None) -> dict:
        name = clean_name(name)
        rows = self._lookup(name)
        if contact_id is not None:
            if type(contact_id) is not int or contact_id < 1:
                raise BridgeError("invalid_contact_id", "Použij skutečné ID karty potvrzené uživatelem.")
            rows = [row for row in rows if row["id"] == contact_id]
        if len(rows) != 1:
            return {"ok": False, "code": "not_found" if not rows else "ambiguous_client",
                    "matches": len(rows), "can_write": False,
                    "candidates": [{"contact_id": r["id"], "phone_suffix": re.sub(r"\D", "", r["phone"])[-3:] or None} for r in rows],
                    "message": "Klient není jednoznačně určený. Upřesni jeho kartu v CRM."}
        contact = rows[0]
        ref = secrets.token_urlsafe(24)
        with self._locked(), self._db() as db:
            db.execute("DELETE FROM refs WHERE expires < ?", (int(self.clock()) - 86400,))
            db.execute("INSERT INTO refs VALUES(?,?,?)", (ref, canonical(contact), int(self.clock()) + 600))
        phone = re.sub(r"\D", "", contact["phone"])
        return {"ok": True, "client_ref": ref, "contact_id": contact["id"],
                "name": contact["first_name"] + " " + contact["last_name"],
                "phone_suffix": phone[-3:] if phone else None, "reference_valid_seconds": 600}

    @staticmethod
    def _operation(value: str):
        if not isinstance(value, str) or not OPERATION.fullmatch(value):
            raise BridgeError("invalid_operation", "ID operace musí mít 8–64 ASCII písmen, číslic, pomlček nebo podtržítek.")

    @staticmethod
    def _note(date: str, notes: str):
        if not isinstance(date, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", date):
            raise BridgeError("invalid_date", "Datum musí být YYYY-MM-DD.")
        try:
            dt.date.fromisoformat(date)
        except ValueError:
            raise BridgeError("invalid_date", "Datum neexistuje.") from None
        if not isinstance(notes, str) or not 2 <= len(notes.strip()) <= 3500 or any(ord(c) < 32 and c != "\n" for c in notes):
            raise BridgeError("invalid_note", "Poznámka musí mít 2–3500 znaků bez řídicích znaků.")
        return notes.strip()

    def save_note(self, client_ref: str, date: str, notes: str, operation_id: str) -> dict:
        self._operation(operation_id)
        notes = self._note(date, notes)
        if not isinstance(client_ref, str) or not REFERENCE.fullmatch(client_ref):
            raise BridgeError("invalid_reference", "Nejdříve vyhledej správného klienta.")
        with self._locked():
            with self._db() as db:
                db.execute("BEGIN IMMEDIATE")
                ref = db.execute("SELECT * FROM refs WHERE ref=?", (client_ref,)).fetchone()
                if ref is None:
                    raise BridgeError("invalid_reference", "Ověřená karta klienta chybí.")
                contact = json.loads(ref["contact"])
                event_hash = digest([contact["id"], date, notes])
                alias = db.execute("SELECT event_hash FROM aliases WHERE operation_id=?", (operation_id,)).fetchone()
                if alias and alias[0] != event_hash:
                    raise BridgeError("operation_conflict", "Toto ID operace už patří jinému zápisu.")
                existing = db.execute("SELECT * FROM events WHERE hash=?", (event_hash,)).fetchone()
                if ref["expires"] < self.clock() and not (existing and existing["state"] == "confirmed"):
                    raise BridgeError("reference_expired", "Ověření klienta vypršelo. Vyhledej jej znovu.")
                if not existing:
                    request_key = "diana_chatgpt_" + hashlib.sha256(operation_id.encode()).hexdigest()[:48]
                    payload = {"contact_id": contact["id"], "notes": notes, "happened_at": date, "request_key": request_key}
                    now = int(self.clock())
                    db.execute("INSERT INTO events VALUES(?,?,?,?,?,NULL,NULL,?,?)",
                               (event_hash, request_key, canonical(contact), canonical(payload), "pending", now, now))
                if not alias:
                    db.execute("INSERT INTO aliases VALUES(?,?)", (operation_id, event_hash))
            return self._deliver(event_hash)

    def retry_note(self, operation_id: str) -> dict:
        self._operation(operation_id)
        with self._locked(), self._db() as db:
            alias = db.execute("SELECT event_hash FROM aliases WHERE operation_id=?", (operation_id,)).fetchone()
            if not alias:
                raise BridgeError("unknown_operation", "Tuto operaci nemám v bezpečné frontě.")
            event_hash = alias[0]
        # Release the connection before network calls. Reacquire the delivery lock.
        with self._locked():
            return self._deliver(event_hash)

    def _deliver(self, event_hash: str) -> dict:
        with self._db() as db:
            event = db.execute("SELECT * FROM events WHERE hash=?", (event_hash,)).fetchone()
        if event["state"] == "confirmed":
            return self._receipt(event, cached=True)
        contact, payload = json.loads(event["contact"]), json.loads(event["payload"])
        try:
            rows = [r for r in self._lookup(clean_name(contact["first_name"] + " " + contact["last_name"])) if r["id"] == contact["id"]]
            if len(rows) != 1 or rows[0] != contact:
                raise BridgeError("client_changed", "Karta klienta se změnila. Zápis je pozastavený.")
            first = self.transport.post("note", payload)
            if type(first.get("id")) is not int or first["id"] < 1:
                raise BridgeError("receipt_unverified", "CRM nevrátilo ověřitelné ID zápisu.")
            # Same request_key and same payload: the backend must return its durable receipt.
            receipt = self.transport.post("note", payload)
            if receipt.get("duplicate") is not True or type(receipt.get("id")) is not int or receipt["id"] != first["id"]:
                raise BridgeError("receipt_unverified", "Uložení nebylo nezávisle potvrzeno. Nezakládej nový zápis.")
        except BridgeError as error:
            state = "blocked" if error.code == "client_changed" else "uncertain"
            with self._db() as db:
                db.execute("UPDATE events SET state=?,last_error=?,updated=? WHERE hash=?",
                           (state, error.code, int(self.clock()), event_hash))
            return {"ok": False, "state": state, "stored_in_crm": None,
                    "code": error.code, "message": str(error), "retry_with_same_operation_id": True}
        with self._db() as db:
            db.execute("UPDATE events SET state='confirmed',activity_id=?,last_error=NULL,updated=? WHERE hash=?",
                       (first["id"], int(self.clock()), event_hash))
            event = db.execute("SELECT * FROM events WHERE hash=?", (event_hash,)).fetchone()
        return self._receipt(event, cached=False)

    @staticmethod
    def _receipt(event, cached: bool) -> dict:
        payload = json.loads(event["payload"])
        return {"ok": True, "state": "confirmed", "stored_in_crm": True,
                "contact_id": payload["contact_id"], "activity_id": event["activity_id"],
                "date": payload["happened_at"], "already_confirmed": cached,
                "crm": "Můj obchod", "kmen_delivery": "not_verified_by_this_tool"}

    def operation_status(self, operation_id: str) -> dict:
        self._operation(operation_id)
        with self._locked(), self._db() as db:
            event = db.execute("SELECT e.* FROM events e JOIN aliases a ON a.event_hash=e.hash WHERE a.operation_id=?", (operation_id,)).fetchone()
        if not event:
            return {"ok": False, "state": "unknown", "stored_in_crm": None}
        if event["state"] == "confirmed":
            return self._receipt(event, cached=True)
        return {"ok": False, "state": event["state"], "stored_in_crm": None,
                "code": event["last_error"], "retry_with_same_operation_id": True}
