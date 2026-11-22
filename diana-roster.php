<?php
declare(strict_types=1);
/* DIANA read-only exact-name search. Fail closed; feature disabled until enabled. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function dianaRosterDeny(int $code): never { http_response_code($code); echo '{"ok":false}'; exit; }
if (!in_array(strtolower((string)($_SERVER['HTTPS']??'')),['on','1'],true) || ($_SERVER['REQUEST_METHOD']??'')!=='POST')dianaRosterDeny(404);
// Reuse existing Diana full CRM authorization, no separate feature flag.
if (getenv('DIANA_CRM_WRITE') !== 'all')dianaRosterDeny(403);
$hash=(string)getenv('DIANA_NOTE_TOKEN_SHA256');
$authorization=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
if(!preg_match('/^[a-f0-9]{64}$/D',$hash))dianaRosterDeny(404);
if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m))dianaRosterDeny(401);
if(!hash_equals($hash,hash('sha256',$m[1])))dianaRosterDeny(401);
$body=file_get_contents('php://input');
if(!is_string($body)||strlen($body)>512)dianaRosterDeny(413);
$input=json_decode($body,true);
$name=is_array($input)?($input['name']??null):null;
if(!is_string($name)||strlen($name)>160||preg_match('/[[:cntrl:]]/',$name))dianaRosterDeny(422);
$name=trim(preg_replace('/\s+/u',' ',$name)??'');
if(substr_count($name,' ')<1||strlen($name)<3)dianaRosterDeny(422);
$owner=filter_var(getenv('DIANA_NOTE_OWNER_ID'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$owner)dianaRosterDeny(403);
try{
 $p=dirname(__DIR__).'/moje-schuzka-data/database.sqlite';
 if(!is_file($p))dianaRosterDeny(503);
 $db=new PDO('sqlite:file:'.$p.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $q=$db->prepare('SELECT id,first_name,last_name,email,phone FROM contacts WHERE user_id=?');
 $q->execute([$owner]);
 $norm=static fn(string $s):string => function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtolower(trim($s));
 $matches=[];
 foreach($q as $r){
  if($norm($r['first_name'].' '.$r['last_name'])!==$norm($name))continue;
  $matches[]=['id'=>(int)$r['id'],'first_name'=>$r['first_name'],'last_name'=>$r['last_name'],
   'email'=>(string)$r['email'],'phone'=>(string)$r['phone']];
  if(count($matches)>20)dianaRosterDeny(409);
 }
 echo json_encode(['ok'=>true,'matches'=>$matches],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){dianaRosterDeny(503);}
