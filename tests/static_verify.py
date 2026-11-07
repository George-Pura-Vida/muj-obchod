from pathlib import Path
from tempfile import TemporaryDirectory
import sqlite3
import subprocess

root=Path(__file__).resolve().parents[1]
files=['ai/lib.php','ai/api.php','ai/index.php','ai/client.js','ai/.htaccess','docs/INTEGRACE.md','docs/ai-secrets.example.php','tools/patch-menu.py']
for f in files:
    assert (root/f).is_file(), f
    assert (root/f).stat().st_size>100, f
for f in ['ai/api.php','ai/lib.php','ai/index.php','docs/ai-secrets.example.php']:
    p=subprocess.run(['php','-l',str(root/f)],capture_output=True,text=True)
    assert p.returncode==0,(f,p.stderr)
with TemporaryDirectory() as temp:
    p=Path(temp)/'index.php'
    p.write_text('<button class="nav-item" data-view="results"><svg><use href="#i-chart"/></svg> Výsledky</button>')
    subprocess.run(['python',str(root/'tools/patch-menu.py'),str(p)],check=True,capture_output=True)
    assert 'ai/index.php' in p.read_text()
    subprocess.run(['python',str(root/'tools/patch-menu.py'),str(p)],check=True,capture_output=True)
    assert p.read_text().count('ai/index.php')==1
    c=sqlite3.connect(':memory:')
    c.executescript('''CREATE TABLE ai_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        activity_id INTEGER NOT NULL, operation TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'reserved', created_at TEXT NOT NULL,
        finished_at TEXT DEFAULT NULL, input_tokens INTEGER DEFAULT NULL,
        output_tokens INTEGER DEFAULT NULL, estimated_usd REAL DEFAULT NULL,
        error_code TEXT DEFAULT NULL);
        CREATE INDEX idx_ai_jobs_user_date ON ai_jobs(user_id, operation, created_at);
    ''')
    c.execute("INSERT INTO ai_jobs(user_id,activity_id,operation,status,created_at) VALUES(?,?,?,?,?)", (1,9,'analyze','reserved','2026-11-07 12:00:00'))
    c.execute("UPDATE ai_jobs SET status=?,finished_at=?,input_tokens=?,output_tokens=?,estimated_usd=?,error_code=? WHERE id=?",('success','2026-11-07 12:01:00',12000,1500,.0195,None,1))
    assert c.execute("SELECT COUNT(*) FROM ai_jobs WHERE user_id=1 AND operation='analyze'").fetchone()[0]==1
print('OK: existující soubory, PHP syntaxe, idempotentní patch a SQL schéma.')
