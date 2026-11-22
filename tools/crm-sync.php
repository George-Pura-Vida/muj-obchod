<?php
/** Owner-only CLI transport between the existing CRM databases on one host. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function enc(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
function digest(mixed $v): string { return hash('sha256', enc($v)); }
function query(PDO $db, string $sql, array $args=[]): array {
    $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function writeq(PDO $db, string $sql, array $args=[]): void { $q=$db->prepare($sql); $q->execute($args); }
function fields(array $row, bool $kmen=false): array {
    return $kmen ? ['first_name'=>(string)($row['firstName']??''), 'last_name'=>(string)($row['lastName']??''),
        'email'=>(string)($row['email']??''), 'phone'=>(string)($row['phone']??'')]
        : ['first_name'=>(string)($row['first_name']??''),'last_name'=>(string)($row['last_name']??''),
           'email'=>(string)($row['email']??''),'phone'=>(string)($row['phone']??'')];
}
function fold(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower(trim($s),'UTF-8') : strtolower(trim($s)); }
function phone(string $s): string { return preg_replace('/^00/','',preg_replace('/[^0-9]/','',$s)); }
function identities(array $f): array {
    $a=[]; if(trim($f['email'])!=='')$a[]='e:'.fold($f['email']);
    if(strlen(phone($f['phone']))>=9)$a[]='p:'.phone($f['phone']); return $a;
}
function sameName(array $a,array $b): bool { return fold($a['first_name'])===fold($b['first_name']) && fold($a['last_name'])===fold($b['last_name']); }
function kclient(string $id,array $f): array {
    $products=[];foreach(['gold','silver','funds','bitcoin','swiss','insurance'] as $p)$products[$p]=['state'=>'verify','evidence'=>''];
    return ['id'=>$id,'firstName'=>$f['first_name'],'lastName'=>$f['last_name'],'email'=>$f['email'],'phone'=>$f['phone'],
        'group'=>'E','amount'=>0,'products'=>$products,'tradePotential'=>'','cooperationPotential'=>'','tradePriority'=>false,
        'cooperationPriority'=>false,'sourceNote'=>'Přeneseno z Mého obchodu; produkty a objemy nejsou doloženy.',
        'nextAction'=>'','nextContact'=>'','sourceDate'=>''];
}
function updateContact(PDO $db,int $oid,string $kid,array $f,string $target): void {
    if($target==='ob')writeq($db,'UPDATE ob.contacts SET first_name=?,last_name=?,email=?,phone=? WHERE id=?',[$f['first_name'],$f['last_name'],$f['email'],$f['phone'],$oid]);
    else {
        $p=json_decode(query($db,'SELECT payload FROM km.clients WHERE id=?',[$kid])[0]['payload'],true,512,JSON_THROW_ON_ERROR);
        $p=array_replace($p,['firstName'=>$f['first_name'],'lastName'=>$f['last_name'],'email'=>$f['email'],'phone'=>$f['phone']]);
        writeq($db,'UPDATE km.clients SET payload=?,revision=revision+1,updated_at=? WHERE id=?',[enc($p),gmdate(DATE_ATOM),$kid]);
    }
}
function activityBody(array $a,array $details): string {
    if(trim((string)($details['opening']??''))===''&&trim((string)($details['findings']??''))===''&&trim((string)($details['summary']??''))==='')return (string)($a['notes']??'');
    $parts=[];
    foreach(['opening'=>'Otevření','notes'=>'Průběh','findings'=>'Zjištění','summary'=>'Shrnutí','outcome'=>'Závěr'] as $key=>$label){
        $value=trim((string)($a[$key]??$details[$key]??''));if($value!=='')$parts[]=$label.': '.$value;
    }
    return implode("\n\n",$parts);
}
function activityKind(string $kind): string {
    if(str_starts_with($kind,'meeting'))return 'meeting';
    return in_array($kind,['phone','email','note'],true)?$kind:'note';
}

function syncRun(string $root,string $owner,bool $dry,bool $fixture): array {
    $root=realpath($root);if(!$root)throw new RuntimeException('root_missing');
    $obfile=$root.'/moje-schuzka-data/database.sqlite';$kmfile=$root.'/muj-kmen-data/muj-kmen.sqlite';
    if(!is_file($obfile)||!is_file($kmfile))throw new RuntimeException('database_missing');
    $private=$root.'/diana-crm-sync-private';
    if(!is_dir($private)&&!mkdir($private,0700))throw new RuntimeException('state_unavailable');
    chmod($private,0700);file_put_contents($private.'/.htaccess',"Require all denied\nDeny from all\n");
    $lock=fopen($private.'/run.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['ok'=>false,'status'=>'already_running'];
    $db=new PDO('sqlite:'.$private.'/sync.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    chmod($private.'/sync.sqlite',0600);$db->exec('PRAGMA busy_timeout=10000');
    $db->exec('CREATE TABLE IF NOT EXISTS contact_pairs(ob INTEGER PRIMARY KEY, km TEXT NOT NULL UNIQUE, ob_hash TEXT NOT NULL, km_hash TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS entry_pairs(ob INTEGER PRIMARY KEY, km TEXT NOT NULL UNIQUE, ob_hash TEXT NOT NULL, km_hash TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS conflicts(type TEXT NOT NULL, ref TEXT NOT NULL, reason TEXT NOT NULL, PRIMARY KEY(type,ref));');
    if(!$dry){
        foreach(['ob'=>$obfile,'km'=>$kmfile] as $key=>$file){
            $backup=$private.'/backup-'.gmdate('Y-m-d').'-'.$key.'.sqlite';
            if(!is_file($backup)){
                $src=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $src->exec('PRAGMA busy_timeout=10000');$src->exec('VACUUM INTO '.$src->quote($backup));chmod($backup,0600);
            }
        }
    }
    $db->exec('ATTACH DATABASE '.$db->quote($obfile).' AS ob');$db->exec('ATTACH DATABASE '.$db->quote($kmfile).' AS km');
    $users=query($db,'SELECT id FROM ob.users WHERE lower(email)=lower(?)',[$owner]);
    if(count($users)!==1)throw new RuntimeException('owner_not_unique');$uid=(int)$users[0]['id'];
    $stats=['ok'=>true,'dry_run'=>$dry,'fixture_only'=>$fixture,'created_ob'=>0,'created_km'=>0,'updated_contacts'=>0,'entries_to_ob'=>0,'entries_to_km'=>0,'conflicts'=>0];
    $conflict=static function(string $type,string $ref,string $reason)use($db,&$stats):void{
        writeq($db,'INSERT INTO conflicts VALUES(?,?,?) ON CONFLICT(type,ref) DO UPDATE SET reason=excluded.reason',[$type,$ref,$reason]);$stats['conflicts']++;
    };
    $db->exec('BEGIN IMMEDIATE');
    try{
        $obs=[];$kms=[];$deleted=[];
        foreach(query($db,'SELECT * FROM ob.contacts WHERE user_id=?',[$uid]) as $row){
            $f=fields($row);if($fixture&&!sameName($f,['first_name'=>'Test','last_name'=>'Testík']))continue;
            $obs[(int)$row['id']]=['fields'=>$f,'row'=>$row];
        }
        foreach(query($db,'SELECT * FROM km.clients') as $row){
            $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$f=fields($p,true);
            if($fixture&&!sameName($f,['first_name'=>'Test','last_name'=>'Testík']))continue;
            if(($row['deleted_at']??'')!==''){$deleted[$row['id']]=$f;continue;}
            $kms[$row['id']]=['fields'=>$f,'row'=>$row];
        }
        $pairs=query($db,'SELECT * FROM contact_pairs');$usedOb=[];$usedKm=[];
        foreach($pairs as $pair){
            $oid=(int)$pair['ob'];$kid=$pair['km'];$usedOb[$oid]=true;$usedKm[$kid]=true;
            if($fixture&&!isset($obs[$oid])&&!isset($kms[$kid]))continue;
            if(!isset($obs[$oid],$kms[$kid])){$conflict('contact',(string)$oid,'missing_or_deleted_no_restore');continue;}
            $of=$obs[$oid]['fields'];$kf=$kms[$kid]['fields'];$oh=digest($of);$kh=digest($kf);
            if($oh===$pair['ob_hash']&&$kh===$pair['km_hash'])continue;
            $oc=$oh!==$pair['ob_hash'];$kc=$kh!==$pair['km_hash'];
            if($oc&&$kc&&$of!==$kf){$conflict('contact',(string)$oid,'concurrent_changes');continue;}
            $new=$oc?$of:$kf;
            // Never introduce a duplicate identifier through an update.
            $collision=false;
            foreach($obs as $id=>$v)if($id!==$oid&&array_intersect(identities($new),identities($v['fields'])))$collision=true;
            foreach($kms as $id=>$v)if($id!==$kid&&array_intersect(identities($new),identities($v['fields'])))$collision=true;
            if($collision){$conflict('contact',(string)$oid,'identifier_collision');continue;}
            if($of!==$new)updateContact($db,$oid,$kid,$new,'ob');
            if($kf!==$new)updateContact($db,$oid,$kid,$new,'km');
            $obs[$oid]['fields']=$kms[$kid]['fields']=$new;
            writeq($db,'UPDATE contact_pairs SET ob_hash=?,km_hash=? WHERE ob=?',[digest($new),digest($new),$oid]);$stats['updated_contacts']++;
        }
        // Only unique email/phone identity establishes a pre-existing pair. Names only block creation.
        foreach($obs as $oid=>$o){
            if(isset($usedOb[$oid]))continue;$hits=[];$same=[];
            foreach($kms as $kid=>$k){
                if(array_intersect(identities($o['fields']),identities($k['fields'])))$hits[]=$kid;
                if(sameName($o['fields'],$k['fields']))$same[]=$kid;
            }
            if(count($hits)>1||count($hits)===1&&isset($usedKm[$hits[0]])){$conflict('contact',(string)$oid,'ambiguous_identity');continue;}
            if(count($hits)===1){$kid=$hits[0];}
            else{
                if($same){$conflict('contact',(string)$oid,'name_requires_explicit_mapping');continue;}
                foreach($deleted as $f)if(sameName($o['fields'],$f)||array_intersect(identities($o['fields']),identities($f)))continue 2;
                $kid='sync-ob-'.$uid.'-'.$oid;
                if(isset($deleted[$kid])){$conflict('contact',(string)$oid,'deleted_no_restore');continue;}
                if(isset($kms[$kid])){$conflict('contact',(string)$oid,'unmapped_source_id_collision');continue;}
                $p=kclient($kid,$o['fields']);writeq($db,'INSERT INTO km.clients(id,payload,revision,updated_at,deleted_at) VALUES(?,?,1,?,?)',[$kid,enc($p),gmdate(DATE_ATOM),'']);
                $kms[$kid]=['fields'=>$o['fields'],'row'=>['id'=>$kid]];$stats['created_km']++;
            }
            writeq($db,'INSERT INTO contact_pairs VALUES(?,?,?,?)',[$oid,$kid,digest($o['fields']),digest($kms[$kid]['fields'])]);
            $usedOb[$oid]=$usedKm[$kid]=true;
        }
        foreach($kms as $kid=>$k){
            if(isset($usedKm[$kid]))continue;$collision=false;
            foreach($obs as $o)if(sameName($k['fields'],$o['fields'])||array_intersect(identities($k['fields']),identities($o['fields'])))$collision=true;
            if($collision){$conflict('contact',$kid,'unmapped_existing_contact');continue;}
            $f=$k['fields'];
            writeq($db,'INSERT INTO ob.contacts(user_id,first_name,last_name,email,phone,source) VALUES(?,?,?,?,?,?)',[$uid,$f['first_name'],$f['last_name'],$f['email'],$f['phone'],'diana-sync:'.$kid]);
            $oid=(int)$db->lastInsertId();writeq($db,'INSERT INTO contact_pairs VALUES(?,?,?,?)',[$oid,$kid,digest($f),digest($f)]);
            $obs[$oid]=['fields'=>$f,'row'=>['id'=>$oid]];$usedOb[$oid]=$usedKm[$kid]=true;$stats['created_ob']++;
        }
        foreach(query($db,'SELECT * FROM contact_pairs') as $pair){
            $oid=(int)$pair['ob'];$kid=$pair['km'];if(!isset($obs[$oid],$kms[$kid]))continue;
            $obentries=query($db,'SELECT a.*,d.opening,d.findings,d.summary FROM ob.activities a LEFT JOIN ob.activity_details d ON d.activity_id=a.id WHERE a.contact_id=? AND a.user_id=?',[$oid,$uid]);
            $kentries=query($db,'SELECT * FROM km.entries WHERE client_id=?',[$kid]);$mappedOb=[];$mappedKm=[];
            foreach(query($db,'SELECT * FROM entry_pairs') as $p){$mappedOb[(int)$p['ob']]=$p;$mappedKm[$p['km']]=$p;}
            foreach($obentries as $a){
                $aid=(int)$a['id'];$body=activityBody($a,$a);$canonical=[activityKind($a['kind']),$a['happened_at'],$body,(string)$a['outcome']];
                if(isset($mappedOb[$aid])){
                    if(digest($canonical)!==$mappedOb[$aid]['ob_hash'])$conflict('entry',(string)$aid,'source_edited_review_required');continue;
                }
                if($body===''||strlen($body)>20000){$conflict('entry',(string)$aid,'empty_or_oversized_body');continue;}
                $keys=query($db,'SELECT request_key FROM ob.activity_request_keys WHERE user_id=? AND activity_id=?',[$uid,$aid]);
                $sourceKeys=array_column($keys,'request_key');
                $matching=[];
                foreach($kentries as $e)if(in_array($e['id'],$sourceKeys,true)&&!isset($mappedKm[$e['id']]))$matching[]=$e;
                if(count($matching)===1){
                    $e=$matching[0];$ec=[$e['kind'],$e['occurred_at'],$e['body'],$e['result']];
                    if($canonical!==$ec){$conflict('entry',(string)$aid,'same_source_key_different_content');continue;}
                    writeq($db,'INSERT INTO entry_pairs VALUES(?,?,?,?)',[$aid,$e['id'],digest($canonical),digest($ec)]);$mappedKm[$e['id']]=true;continue;
                }
                $eid='sync-ob-'.$uid.'-'.$aid;
                $existing=query($db,'SELECT * FROM km.entries WHERE id=?',[$eid]);
                if($existing){$conflict('entry',(string)$aid,'unmapped_entry_id_collision');continue;}
                writeq($db,'INSERT INTO km.entries VALUES(?,?,?,?,?,?,?)',[$eid,$kid,$canonical[0],$body,$canonical[3],$canonical[1],gmdate(DATE_ATOM)]);
                $kh=digest([$canonical[0],$canonical[1],$body,$canonical[3]]);
                writeq($db,'INSERT INTO entry_pairs VALUES(?,?,?,?)',[$aid,$eid,digest($canonical),$kh]);$mappedKm[$eid]=true;$stats['entries_to_km']++;
                writeq($db,'UPDATE km.clients SET revision=revision+1,updated_at=? WHERE id=?',[gmdate(DATE_ATOM),$kid]);
            }
            foreach($kentries as $e){
                $canonical=[$e['kind'],$e['occurred_at'],$e['body'],$e['result']];
                if(isset($mappedKm[$e['id']])){
                    if(is_array($mappedKm[$e['id']])&&digest($canonical)!==$mappedKm[$e['id']]['km_hash'])$conflict('entry',$e['id'],'source_edited_review_required');continue;
                }
                if(!in_array($e['kind'],['phone','meeting','email','note'],true)){$conflict('entry',$e['id'],'unsupported_kind');continue;}
                writeq($db,'INSERT INTO ob.activities(user_id,contact_id,kind,happened_at,notes,outcome) VALUES(?,?,?,?,?,?)',[$uid,$oid,$e['kind'],$e['occurred_at'],$e['body'],$e['result']]);
                $aid=(int)$db->lastInsertId();$rendered=activityBody(['notes'=>$e['body'],'outcome'=>$e['result']],[]);
                writeq($db,'INSERT INTO entry_pairs VALUES(?,?,?,?)',[$aid,$e['id'],digest([$e['kind'],$e['occurred_at'],$rendered,$e['result']]),digest($canonical)]);$stats['entries_to_ob']++;
            }
        }
        $stats['mapped_contacts']=(int)$db->query('SELECT COUNT(*) FROM contact_pairs')->fetchColumn();
        if($dry)$db->exec('ROLLBACK');else $db->exec('COMMIT');
    }catch(Throwable $e){try{$db->exec('ROLLBACK');}catch(Throwable $ignored){}throw $e;}
    $stats['checked_at']=gmdate(DATE_ATOM);
    if(!$dry){$temp=$private.'/status.tmp';file_put_contents($temp,enc($stats));chmod($temp,0600);rename($temp,$private.'/status.json');}
    flock($lock,LOCK_UN);fclose($lock);return $stats;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $options=getopt('',['root:','owner:','dry-run','fixture-only']);
    try{echo enc(syncRun($options['root']??dirname(__DIR__,2),$options['owner']??'jgjanousek@gmail.com',isset($options['dry-run']),isset($options['fixture-only'])))."\n";}
    catch(Throwable $e){fwrite(STDERR,enc(['ok'=>false,'status'=>'blocked','error_class'=>get_class($e)])."\n");exit(1);}
}
