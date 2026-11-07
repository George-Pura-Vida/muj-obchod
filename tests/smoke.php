<?php
declare(strict_types=1);
require __DIR__ . '/../ai/lib.php';
if (!extension_loaded('pdo_sqlite')) {fwrite(STDERR, "SKIP: PDO SQLite nenainstalováno\n"); exit(2);}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE ai_jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,activity_id INTEGER NOT NULL,operation TEXT NOT NULL,status TEXT NOT NULL,created_at TEXT NOT NULL,finished_at TEXT,input_tokens INTEGER,output_tokens INTEGER,estimated_usd REAL,error_code TEXT);');
$cfg = ['monthly_transcriptions'=>20,'daily_transcriptions'=>5,'monthly_analyses'=>40,'daily_analyses'=>10];
$id=ai_claim($db,1,20,'transcribe',$cfg);
assert($id > 0);
ai_finish($db,$id,'success');
assert((int)$db->query('SELECT COUNT(*) FROM ai_jobs')->fetchColumn()===1);
assert((int)$db->query("SELECT COUNT(*) FROM ai_jobs WHERE status='success'")->fetchColumn()===1);
$id2=ai_claim($db,2,20,'transcribe',$cfg);
assert($id2 > $id);
assert((int)$db->query('SELECT COUNT(DISTINCT user_id) FROM ai_jobs')->fetchColumn()===2);
$schema=ai_schema();
assert(count($schema['schema']['required'])===9);
assert($schema['strict']===true);
$summary=ai_review_text(['summary'=>'Podařilo se','coaching_score_0_10'=>7,'improvements'=>['Více otevřených otázek']]);
assert(str_contains($summary, 'Více otevřených otázek'));
echo "OK: databázová rezervace, oddělení uživatelů, aktualizace spotřeby, schéma JSON a formát zprávy.\n";
