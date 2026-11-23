<?php
declare(strict_types=1);
require __DIR__.'/delete-record.php';
require __DIR__.'/account-admin.php';
function check(bool $value,string $message): void { if(!$value)throw new RuntimeException($message); }
function fail(string $message,int $status=400): never { throw new DomainException($message,$status); }
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$api=file_get_contents(__DIR__.'/api.php');
$start=strpos($api,'$db->exec("CREATE TABLE');
$end=strpos($api,'");',$start);
$schema=substr($api,$start+11,$end-$start-11);
$db->exec($schema);
$db->exec('CREATE TABLE audit_log(event TEXT,actor_id INTEGER,target_id INTEGER,detail TEXT)');
$db->exec("INSERT INTO users(id,email,password_hash) VALUES(1,'a@example.invalid','x'),(2,'b@example.invalid','x')");
$db->exec("INSERT INTO contacts(id,user_id,first_name,last_name) VALUES(1,1,'A','One'),(2,2,'B','Two')");
$db->exec("INSERT INTO activities(id,user_id,contact_id,kind,happened_at) VALUES(1,1,1,'note','2026-11-23'),(2,1,1,'note','2026-11-23'),(3,2,2,'note','2026-11-23')");
$db->exec("INSERT INTO activity_details(activity_id,followup_activity_id) VALUES(1,2)");
$db->exec("INSERT INTO activity_conversation(activity_id,topic_notes) VALUES(2,'{}')");
$db->exec("INSERT INTO coaching_scorecards(activity_id,scores) VALUES(2,'{}')");
$db->exec("INSERT INTO activity_request_keys VALUES(1,'test',2,'hash')");
$private=sys_get_temp_dir().'/crm-delete-test-'.bin2hex(random_bytes(8));
mkdir($private.'/recordings',0700,true);
try {
 file_put_contents($private.'/recordings/test.webm','audio');
 $db->exec("INSERT INTO recordings(user_id,activity_id,filename,original_name,mime,bytes) VALUES(1,2,'test.webm','test.webm','audio/webm',5)");
 foreach(['activity_delete'=>3,'contact_delete'=>2] as $action=>$id) {
  try { deleteOwnedRecord($db,1,$action,$id,$private); throw new RuntimeException('Cross-owner delete accepted'); }
  catch(DomainException $e) { check($e->getCode()===404,'Expected 404'); }
 }
 check(! $db->inTransaction(),'Missing-row transaction must roll back');
 $result=deleteOwnedRecord($db,1,'activity_delete',2,$private);
 check($result['ok']&&$result['cleanup_pending']===0,'Activity delete failed');
 check(!file_exists($private.'/recordings/test.webm'),'Audio must be removed');
 check((int)$db->query('SELECT COUNT(*) FROM contacts')->fetchColumn()===2,'Activity delete removed contact');
 check($db->query('SELECT followup_activity_id FROM activity_details WHERE activity_id=1')->fetchColumn()===null,'Followup link must reset');
 foreach(['recordings','coaching_scorecards','activity_conversation','activity_request_keys'] as $table)check((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn()===0,"Cascade failed: $table");
 $db->exec("INSERT INTO contracts(user_id,contact_id,product) VALUES(1,1,'Test')");
 $db->exec("INSERT INTO opportunities(user_id,contact_id,product) VALUES(1,1,'Test')");
 $db->exec("INSERT INTO referrals(user_id,contact_id,name) VALUES(1,1,'Test')");
 $db->exec("INSERT INTO collaboration_profiles(contact_id,stage) VALUES(1,'partner')");
 deleteOwnedRecord($db,1,'contact_delete',1,$private);
 foreach(['contracts','opportunities','referrals','collaboration_profiles','activity_details'] as $table)check((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn()===0,"Contact cascade failed: $table");
 check((int)$db->query('SELECT COUNT(*) FROM contacts WHERE id=2')->fetchColumn()===1,'Other owner contact removed');
 check((int)$db->query('SELECT COUNT(*) FROM activities WHERE id=3')->fetchColumn()===1,'Other owner activity removed');
 check((int)$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===2,'Deletes not audited');
 foreach(['viewer','user','editor'] as $role) {
  $access=permissions(['role'=>$role,'permissions'=>'{}']);
  foreach(['contact_delete','activity_delete'] as $action) {
   check(!$access['actions'][$action],"$role got deletion by default");
   try { authorizeWrite($access,$action,[]); throw new RuntimeException('Permission bypass'); }
   catch(DomainException $e) { check($e->getCode()===403,'Expected forbidden'); }
  }
 }
 $admin=permissions(['role'=>'admin','permissions'=>'{}']);
 foreach(['contact_delete','activity_delete'] as $action)authorizeWrite($admin,$action,[]);
 echo "PASS: ownership, cascades, followup links, audio cleanup, audit and deletion rights\n";
} finally {
 foreach(glob($private.'/recordings/*') as $file)unlink($file);
 rmdir($private.'/recordings');rmdir($private);
}
