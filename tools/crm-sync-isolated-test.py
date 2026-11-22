#!/usr/bin/env python3
"""Isolated integration tests; downloads Ubuntu PHP packages without installing system packages."""
import hashlib,json,os,sqlite3,subprocess,tempfile,urllib.request
from pathlib import Path
stage=Path(tempfile.mkdtemp(prefix="diana-crm-test-"))
runtime=stage/"runtime"
runtime.mkdir()
p=subprocess.run(["apt-get","download","php8.3-cli","php8.3-common","php8.3-sqlite3"],cwd=stage,capture_output=True,text=True)
if p.returncode: raise SystemExit("BLOCKED: official PHP runtime download failed")
for deb in stage.glob("*.deb"):
    subprocess.run(["dpkg-deb","-x",str(deb),str(runtime)],check=True,capture_output=True)
php=runtime/"usr/bin/php8.3"
extension=next(runtime.glob("usr/lib/php/*/pdo_sqlite.so")).parent
base=[str(php),"-n","-d","extension="+str(extension/"pdo.so"),"-d","extension="+str(extension/"pdo_sqlite.so")]
worker=stage/"crm-sync.php"
url="https://raw.githubusercontent.com/george-pura-vida/muj-obchod/fe9eab6d32c034c6be785e2badae3e4b1bcfa1d6/tools/crm-sync.php"
worker.write_bytes(urllib.request.urlopen(url,timeout=30).read())
lint=subprocess.run(base+["-l",str(worker)],capture_output=True,text=True)
if lint.returncode: raise SystemExit("LINT_FAILED "+lint.stdout)
print("PHP_LINT_OK")
root=stage/"fixture"; (root/"moje-schuzka-data").mkdir(parents=True); (root/"muj-kmen-data").mkdir()
ob=sqlite3.connect(root/"moje-schuzka-data/database.sqlite")
km=sqlite3.connect(root/"muj-kmen-data/muj-kmen.sqlite")
ob.executescript("""
CREATE TABLE users(id INTEGER PRIMARY KEY,email TEXT);
INSERT INTO users VALUES(1,'jgjanousek@gmail.com');
CREATE TABLE contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,first_name TEXT,last_name TEXT,email TEXT DEFAULT '',phone TEXT DEFAULT '',source TEXT DEFAULT '');
CREATE TABLE activities(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,contact_id INTEGER,kind TEXT,happened_at TEXT,notes TEXT,outcome TEXT DEFAULT '');
CREATE TABLE activity_details(activity_id INTEGER PRIMARY KEY,opening TEXT,findings TEXT,summary TEXT);
CREATE TABLE activity_request_keys(user_id INTEGER,request_key TEXT,activity_id INTEGER,payload_hash TEXT,PRIMARY KEY(user_id,request_key));
INSERT INTO contacts VALUES(1,1,'Test','Testík','','','');
INSERT INTO activities VALUES(1,1,1,'meeting_first','2026-11-22','TEST-BODY','TECHNICAL TEST');
INSERT INTO activity_details VALUES(1,'TEST-OPENING','TEST-FINDINGS','TEST-SUMMARY');
""")
km.executescript("""
CREATE TABLE clients(id TEXT PRIMARY KEY,payload TEXT,revision INTEGER,updated_at TEXT,deleted_at TEXT DEFAULT '');
CREATE TABLE entries(id TEXT PRIMARY KEY,client_id TEXT,kind TEXT,body TEXT,result TEXT,occurred_at TEXT,created_at TEXT);
""")
ob.commit();km.commit()
def run(*flags,ok=True):
    p=subprocess.run(base+[str(worker),"--root",str(root),*flags],capture_output=True,text=True)
    if (p.returncode==0)!=ok: raise AssertionError("worker result: "+p.stdout+p.stderr)
    return json.loads(p.stdout) if p.stdout else {}
def count(db,t): return db.execute("SELECT COUNT(*) FROM "+t).fetchone()[0]
def client(cid,first,last,email=""):
    return {"id":cid,"firstName":first,"lastName":last,"email":email,"phone":""}
r=run("--dry-run","--fixture-only")
assert count(km,"clients")==0 and count(km,"entries")==0
print("DRY_RUN_ROLLBACK_OK")
r=run("--fixture-only");assert r["created_km"]==1 and r["entries_to_km"]==1
e=km.execute("SELECT body FROM entries").fetchone()[0]
assert all(s in e for s in ("TEST-BODY","TEST-OPENING","TEST-FINDINGS","TEST-SUMMARY"))
print("OBCHOD_TO_KMEN_FULL_BODY_OK")
r=run("--fixture-only");assert r["created_km"]==0 and r["entries_to_km"]==0 and r["conflicts"]==0
assert count(km,"entries")==1
print("RETRY_WITHOUT_DUPLICATES_OK")
km.execute("INSERT INTO clients VALUES(?,?,1,'','')",("km-new",json.dumps(client("km-new","Synthetic","Reverse","reverse@example.invalid"))))
km.execute("INSERT INTO entries VALUES('km-note','km-new','note','REVERSE-BODY','','2026-11-22','')")
km.commit();r=run();assert r["created_ob"]==1 and r["entries_to_ob"]==1
assert ob.execute("SELECT notes FROM activities WHERE notes='REVERSE-BODY'").fetchone()
r=run();assert r["entries_to_ob"]==r["entries_to_km"]==0 and r["conflicts"]==0
print("KMEN_TO_OBCHOD_AND_NO_ECHO_OK")
ob.execute("UPDATE contacts SET phone='+420123456789' WHERE id=1");ob.commit();r=run()
p=json.loads(km.execute("SELECT payload FROM clients WHERE id='sync-ob-1-1'").fetchone()[0]);assert p["phone"]=="+420123456789"
p["email"]="fixture@example.invalid";km.execute("UPDATE clients SET payload=? WHERE id='sync-ob-1-1'",(json.dumps(p),));km.commit();r=run()
assert ob.execute("SELECT email FROM contacts WHERE id=1").fetchone()[0]=="fixture@example.invalid"
print("CONTACT_UPDATES_BOTH_DIRECTIONS_OK")
ob.execute("UPDATE contacts SET phone='111111111' WHERE id=1");ob.commit()
p["phone"]="222222222";km.execute("UPDATE clients SET payload=? WHERE id='sync-ob-1-1'",(json.dumps(p),));km.commit()
r=run();assert r["conflicts"]>=1
assert ob.execute("SELECT phone FROM contacts WHERE id=1").fetchone()[0]=="111111111"
assert json.loads(km.execute("SELECT payload FROM clients WHERE id='sync-ob-1-1'").fetchone()[0])["phone"]=="222222222"
print("CONCURRENT_CONFLICT_PRESERVED_OK")
km.execute("UPDATE clients SET deleted_at='2026-11-22' WHERE id='sync-ob-1-1'");km.commit();run()
assert km.execute("SELECT deleted_at FROM clients WHERE id='sync-ob-1-1'").fetchone()[0]
print("DELETED_CLIENT_NOT_RESTORED_OK")
ob.execute("INSERT INTO contacts(user_id,first_name,last_name,email) VALUES(1,'Synthetic','Match','match@example.invalid')")
km.execute("INSERT INTO clients VALUES(?,?,1,'','')",("match",json.dumps(client("match","Synthetic","Match","match@example.invalid"))))
ob.commit();km.commit();before=(count(ob,"contacts"),count(km,"clients"));run()
assert before==(count(ob,"contacts"),count(km,"clients"))
print("UNIQUE_IDENTITY_LINK_WITHOUT_DUPLICATE_OK")
ob.execute("INSERT INTO contacts(user_id,first_name,last_name) VALUES(1,'Ambiguous','Identity')")
km.execute("INSERT INTO clients VALUES(?,?,1,'','')",("ambiguous",json.dumps(client("ambiguous","Ambiguous","Identity"))))
ob.commit();km.commit();before=(count(ob,"contacts"),count(km,"clients"));r=run()
assert before==(count(ob,"contacts"),count(km,"clients")) and r["conflicts"]>=1
print("NAME_ONLY_MATCH_BLOCKED_OK")
ob.execute("INSERT INTO contacts(user_id,first_name,last_name) VALUES(1,'Atomic','Failure')")
newid=ob.execute("SELECT last_insert_rowid()").fetchone()[0]
ob.execute("INSERT INTO activities(user_id,contact_id,kind,happened_at,notes) VALUES(1,?,'note','2026-11-22','FAIL-ME')",(newid,));ob.commit()
km.execute("CREATE TRIGGER fail_insert BEFORE INSERT ON entries WHEN NEW.body='FAIL-ME' BEGIN SELECT RAISE(ABORT,'test'); END");km.commit()
before=count(km,"clients");run(ok=False)
assert count(km,"clients")==before
km.execute("DROP TRIGGER fail_insert");km.commit();run()
assert count(km,"clients")==before+1
print("FAILED_TRANSFER_ATOMIC_ROLLBACK_AND_RETRY_OK")
print("ALL_TESTS_PASSED")
