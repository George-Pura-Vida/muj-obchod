<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/crm-sync.php';
$root=__DIR__;
syncRun($root,'jgjanousek@gmail.com',false,true);
$ledger=new PDO('sqlite:'.$root.'/diana-crm-sync-private/sync.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$ob=new PDO('sqlite:'.$root.'/moje-schuzka-data/database.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$km=new PDO('sqlite:'.$root.'/muj-kmen-data/muj-kmen.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pairs=$ledger->query('SELECT ob,km FROM contact_pairs')->fetchAll(PDO::FETCH_ASSOC);$fixture=null;
foreach($pairs as $pair){
 $q=$ob->prepare('SELECT first_name,last_name FROM contacts WHERE id=?');$q->execute([$pair['ob']]);$f=$q->fetch(PDO::FETCH_ASSOC);
 $q=$km->prepare("SELECT payload FROM clients WHERE id=? AND deleted_at=''");$q->execute([$pair['km']]);$raw=$q->fetchColumn();if(!$raw)continue;
 $k=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if($f&&$f['first_name']==='Test'&&$f['last_name']==='Testík'&&$k['firstName']==='Test'&&$k['lastName']==='Testík'){$fixture=$pair;break;}
}
if(!$fixture)throw new RuntimeException('fixture_missing');
$id='sync-test-reverse-20261122';
$body='TEST DIANA 2026-11-22: ověření směru Můj kmen do Mého obchodu. Pouze fiktivní technická poznámka.';
$q=$km->prepare('INSERT OR IGNORE INTO entries VALUES(?,?,?,?,?,?,?)');
$q->execute([$id,$fixture['km'],'note',$body,'Technický test','2026-11-22',gmdate(DATE_ATOM)]);
$km=null;$ob=null;$ledger=null;
$first=syncRun($root,'jgjanousek@gmail.com',false,true);
$repeat=syncRun($root,'jgjanousek@gmail.com',false,true);
$ob=new PDO('sqlite:'.$root.'/moje-schuzka-data/database.sqlite');
$km=new PDO('sqlite:'.$root.'/muj-kmen-data/muj-kmen.sqlite');
$q=$ob->prepare('SELECT COUNT(*) FROM activities WHERE contact_id=? AND notes=?');$q->execute([$fixture['ob'],$body]);$oc=(int)$q->fetchColumn();
$q=$km->prepare('SELECT COUNT(*) FROM entries WHERE client_id=? AND id=?');$q->execute([$fixture['km'],$id]);$kc=(int)$q->fetchColumn();
$proof=['ok'=>$oc===1&&$kc===1&&$repeat['entries_to_ob']===0&&$repeat['entries_to_km']===0,
 'reverse_count_ob'=>$oc,'reverse_count_km'=>$kc,'repeat_created_entries_ob'=>$repeat['entries_to_ob'],
 'repeat_created_entries_km'=>$repeat['entries_to_km'],'created_contacts_on_repeat'=>$repeat['created_ob']+$repeat['created_km'],
 'mapped_fixture'=>true,'checked_at'=>gmdate(DATE_ATOM)];
file_put_contents($root.'/diana-crm-sync-private/proof.json',enc($proof));chmod($root.'/diana-crm-sync-private/proof.json',0600);
echo enc($proof)."\n";
