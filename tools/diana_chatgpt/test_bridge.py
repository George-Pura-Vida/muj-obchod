import concurrent.futures
import copy
import os
from pathlib import Path
import tempfile
import unittest

from bridge import Bridge, BridgeError, CRMTransport, canonical


CONTACT = {"id": 7, "first_name": "Test", "last_name": "Testovací",
           "email": "fixture@example.invalid", "phone": "123456789"}


class FakeCRM:
    def __init__(self):
        self.contacts = [copy.deepcopy(CONTACT)]
        self.notes = {}
        self.note_calls = []
        self.lose_first_reply = False
        self.bad_verification = False

    def post(self, endpoint, payload):
        if endpoint == "roster":
            return {"ok": True, "matches": copy.deepcopy(self.contacts)}
        self.note_calls.append(copy.deepcopy(payload))
        key = payload["request_key"]
        previous = self.notes.get(key)
        if previous:
            if previous[0] != canonical(payload):
                raise BridgeError("crm_http_409", "Konflikt")
            return {"ok": True, "id": previous[1], "duplicate": not self.bad_verification}
        new_id = len(self.notes) + 1
        self.notes[key] = (canonical(payload), new_id)
        if self.lose_first_reply:
            self.lose_first_reply = False
            raise BridgeError("crm_unreachable", "Odpověď po potvrzeném commitu se ztratila.")
        return {"ok": True, "id": new_id}


class BridgeTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state"
        self.crm = FakeCRM()
        self.now = [1800000000.0]
        self.bridge = Bridge(self.path, self.crm, lambda: self.now[0])

    def tearDown(self):
        self.tmp.cleanup()

    def ref(self):
        return self.bridge.find_client("Test Testovací")["client_ref"]

    def submit(self, ref=None, operation="voice_note_001", notes="Fiktivní zápis."):
        return self.bridge.save_note(ref or self.ref(), "2026-11-23", notes, operation)

    def test_verified_write_uses_one_key_twice(self):
        result = self.submit()
        self.assertTrue(result["stored_in_crm"])
        self.assertEqual(result["activity_id"], 1)
        self.assertEqual(len(self.crm.notes), 1)
        self.assertEqual(self.crm.note_calls[0], self.crm.note_calls[1])

    def test_repeat_after_restart_is_local_receipt(self):
        ref = self.ref()
        first = self.submit(ref)
        restarted = Bridge(self.path, self.crm, lambda: self.now[0])
        second = restarted.save_note(ref, "2026-11-23", "Fiktivní zápis.", "voice_note_001")
        self.assertEqual(first["activity_id"], second["activity_id"])
        self.assertTrue(second["already_confirmed"])
        self.assertEqual(len(self.crm.note_calls), 2)

    def test_new_operation_same_content_is_not_a_duplicate(self):
        first = self.submit(operation="voice_note_001")
        second = self.submit(operation="voice_note_002")
        self.assertEqual(first["activity_id"], second["activity_id"])
        self.assertEqual(len(self.crm.notes), 1)
        self.assertTrue(self.bridge.operation_status("voice_note_002")["stored_in_crm"])

    def test_operation_id_cannot_be_reused_for_other_content(self):
        self.submit()
        with self.assertRaises(BridgeError) as error:
            self.submit(notes="Jiný obsah.")
        self.assertEqual(error.exception.code, "operation_conflict")
        self.assertEqual(len(self.crm.notes), 1)

    def test_alias_is_also_immutable(self):
        self.submit()
        self.submit(operation="voice_note_002")
        with self.assertRaises(BridgeError):
            self.submit(operation="voice_note_002", notes="Jiný obsah.")

    def test_lost_response_retry_after_restart_does_not_create_second_note(self):
        self.crm.lose_first_reply = True
        result = self.submit()
        self.assertEqual(result["state"], "uncertain")
        self.assertIsNone(result["stored_in_crm"])
        self.assertEqual(len(self.crm.notes), 1)
        restarted = Bridge(self.path, self.crm, lambda: self.now[0])
        self.assertTrue(restarted.retry_note("voice_note_001")["stored_in_crm"])
        self.assertEqual(len(self.crm.notes), 1)

    def test_no_success_when_receipt_cannot_be_verified(self):
        self.crm.bad_verification = True
        result = self.submit()
        self.assertFalse(result["ok"])
        self.assertEqual(result["code"], "receipt_unverified")
        self.assertIsNone(result["stored_in_crm"])

    def test_ambiguous_name_produces_no_reference_and_no_write(self):
        self.crm.contacts.append(dict(CONTACT, id=8))
        result = self.bridge.find_client("Test Testovací")
        self.assertEqual(result["code"], "ambiguous_client")
        self.assertNotIn("client_ref", result)
        self.assertFalse(self.crm.notes)

    def test_boolean_or_duplicate_contact_ids_are_rejected(self):
        for contacts in ([dict(CONTACT, id=True)], [CONTACT, CONTACT]):
            self.crm.contacts = contacts
            with self.assertRaises(BridgeError):
                self.ref()

    def test_ambiguous_client_can_be_selected_by_confirmed_id(self):
        self.crm.contacts.append(dict(CONTACT, id=8, phone="987654321"))
        ref = self.bridge.find_client("Test Testovací", contact_id=8)["client_ref"]
        result = self.submit(ref)
        self.assertEqual(result["contact_id"], 8)
        self.assertEqual(self.crm.note_calls[0]["contact_id"], 8)

    def test_unrelated_id_cannot_be_selected(self):
        result = self.bridge.find_client("Test Testovací", contact_id=999)
        self.assertEqual(result["code"], "not_found")
        self.assertNotIn("client_ref", result)

    def test_client_change_blocks_write(self):
        ref = self.ref()
        self.crm.contacts[0]["phone"] = "987654321"
        result = self.submit(ref)
        self.assertEqual(result["state"], "blocked")
        self.assertEqual(result["code"], "client_changed")
        self.assertFalse(self.crm.notes)

    def test_client_deleted_after_lookup_blocks_write(self):
        ref = self.ref()
        self.crm.contacts = []
        self.assertEqual(self.submit(ref)["code"], "client_changed")
        self.assertFalse(self.crm.notes)

    def test_expired_reference_never_starts_new_delivery(self):
        ref = self.ref()
        self.now[0] += 601
        with self.assertRaises(BridgeError) as error:
            self.submit(ref)
        self.assertEqual(error.exception.code, "reference_expired")
        self.assertFalse(self.crm.notes)

    def test_status_of_unknown_or_pending_never_writes(self):
        self.assertEqual(self.bridge.operation_status("voice_note_001")["state"], "unknown")
        self.crm.lose_first_reply = True
        self.submit()
        count = len(self.crm.note_calls)
        self.assertEqual(self.bridge.operation_status("voice_note_001")["state"], "uncertain")
        self.assertEqual(count, len(self.crm.note_calls))

    def test_invalid_dates_and_notes_do_not_write(self):
        ref = self.ref()
        for date, notes in (("2026-02-30", "Test"), ("20261123", "Test"),
                            ("2026-11-23", "x" * 3501), ("2026-11-23", "x\x00y")):
            with self.assertRaises(BridgeError):
                self.bridge.save_note(ref, date, notes, "voice_note_001")
        self.assertFalse(self.crm.notes)

    def test_parallel_identical_submissions_create_one_note(self):
        ref = self.ref()
        with concurrent.futures.ThreadPoolExecutor(max_workers=4) as executor:
            results = list(executor.map(lambda _: self.submit(ref), range(8)))
        self.assertTrue(all(r["activity_id"] == 1 for r in results))
        self.assertEqual(len(self.crm.note_calls), 2)
        self.assertEqual(len(self.crm.notes), 1)

    def test_state_files_are_private(self):
        self.submit()
        self.assertEqual(self.path.stat().st_mode & 0o777, 0o700)
        for name in ("events.sqlite3", "delivery.lock"):
            self.assertEqual((self.path / name).stat().st_mode & 0o777, 0o600)

    def test_state_database_symlink_is_rejected(self):
        directory = Path(self.tmp.name) / "other"
        directory.mkdir(mode=0o700)
        target = Path(self.tmp.name) / "target"
        target.write_text("untouched")
        (directory / "events.sqlite3").symlink_to(target)
        with self.assertRaises(BridgeError):
            Bridge(directory, self.crm)
        self.assertEqual(target.read_text(), "untouched")

    def test_token_world_readable_or_symlink_is_rejected(self):
        token = Path(self.tmp.name) / "token"
        token.write_text("a" * 64)
        token.chmod(0o644)
        with self.assertRaises(BridgeError):
            CRMTransport(token).post("roster", {"name": "Test Testovací"})
        token.chmod(0o600)
        symlink = Path(self.tmp.name) / "link"
        symlink.symlink_to(token)
        with self.assertRaises(BridgeError):
            CRMTransport(symlink).post("roster", {"name": "Test Testovací"})

    def test_prague_date_handles_utc_midnight_boundary(self):
        import datetime
        self.now[0] = datetime.datetime(2026, 11, 22, 23, 30, tzinfo=datetime.timezone.utc).timestamp()
        self.assertEqual(self.bridge.context()["today"], "2026-11-23")


if __name__ == "__main__":
    unittest.main()
