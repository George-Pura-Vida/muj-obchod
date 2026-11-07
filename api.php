<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
ini_set('session.use_strict_mode', '1');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('moje_schuzka_sid');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));

function reply(array $value, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
function fail(string $message, int $status=400): never { reply(['ok'=>false, 'error'=>$message],$status); }
function body(): array {
    $value=json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($value)) fail('Neplatná data formuláře.'); return $value;
}
function val(array $data,string $key,int $max=5000): string {
    return mb_limit(trim((string)($data[$key]??'')),$max);
}
function mb_limit(string $value,int $max): string {
    return function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max);
}
function money($value): float {
    if($value === '' || $value === null) return 0;
    if(!is_numeric($value)) fail('Částka musí být číslo.');
    $f=(float)$value; if(!is_finite($f)||abs($f)>100000000000)fail('Neplatná částka.'); return round($f,2);
}
function dateValue(array $data,string $key): ?string {
    $d=trim((string)($data[$key]??'')); if($d==='')return null;
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$d);
    if(!$parsed||$parsed->format('Y-m-d')!==$d)fail('Neplatné datum: '.$key); return $d;
}
function csrf(): void {
    $provided=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??'');
    if(!$provided||!hash_equals($_SESSION['csrf']??'',$provided))fail('Bezpečnostní token vypršel. Obnov stránku.',403);
}
function currentUser(): int { $id=(int)($_SESSION['uid']??0); if(!$id)fail('Pro tuto akci se přihlas.',401);return $id; }
function belongs(PDO $db,int $uid,int $contactId): void {
    $q=$db->prepare('SELECT 1 FROM contacts WHERE id=? AND user_id=?'); $q->execute([$contactId,$uid]);
    if(!$q->fetchColumn())fail('Klient nebyl nalezen.',404);
}
function existsOwner(PDO $db,string $table,int $id,int $uid): bool {
    $q=$db->prepare("SELECT 1 FROM $table WHERE id=? AND user_id=?");
    $q->execute([$id,$uid]); return (bool)$q->fetchColumn();
}
function setStage(PDO $db,int $uid,int $cid,string $stage): void {
    $db->prepare('UPDATE contacts SET stage=? WHERE id=? AND user_id=?')->execute([$stage,$cid,$uid]);
}
$action=(string)($_GET['action']??'meta');
try {
    if(!extension_loaded('pdo_sqlite'))fail('Server potřebuje PHP rozšíření PDO SQLite.',503);
    $private=dirname(__DIR__).'/moje-schuzka-data';
    if(!is_dir($private) && !@mkdir($private,0700,true) && !is_dir($private))fail('Nelze vytvořit privátní datový adresář mimo veřejný web.',503);
    $db=new PDO('sqlite:'.$private.'/database.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS login_attempts (identity TEXT PRIMARY KEY, failed INTEGER NOT NULL DEFAULT 0, last_try INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, first_name TEXT NOT NULL, last_name TEXT NOT NULL, phone TEXT DEFAULT '', email TEXT DEFAULT '', address TEXT DEFAULT '', source TEXT DEFAULT '', notes TEXT DEFAULT '', stage TEXT NOT NULL DEFAULT 'contact', care_months INTEGER DEFAULT 3, next_care TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS activities (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, kind TEXT NOT NULL, happened_at TEXT NOT NULL, notes TEXT DEFAULT '', outcome TEXT DEFAULT '', next_at TEXT, transcript TEXT DEFAULT '', review TEXT DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS contracts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, product TEXT NOT NULL, value REAL NOT NULL DEFAULT 0, commission_expected REAL NOT NULL DEFAULT 0, expected_at TEXT, commission_paid REAL NOT NULL DEFAULT 0, paid_at TEXT, status TEXT NOT NULL DEFAULT 'pending', notes TEXT DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS opportunities (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, product TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'crosssell', status TEXT NOT NULL DEFAULT 'open', potential REAL DEFAULT 0, expected_commission REAL DEFAULT 0, next_at TEXT, notes TEXT DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS referrals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, name TEXT NOT NULL, phone TEXT DEFAULT '', permission INTEGER NOT NULL DEFAULT 0, notes TEXT DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS recordings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, activity_id INTEGER NOT NULL, filename TEXT NOT NULL, original_name TEXT NOT NULL, mime TEXT NOT NULL, bytes INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(activity_id) REFERENCES activities(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_contacts_user ON contacts(user_id);
CREATE INDEX IF NOT EXISTS idx_activities_contact ON activities(contact_id,user_id);
CREATE INDEX IF NOT EXISTS idx_contracts_contact ON contracts(contact_id,user_id);
CREATE INDEX IF NOT EXISTS idx_opportunities_contact ON opportunities(contact_id,user_id);");
    $userCount=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $cfg=is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
    if(!is_array($cfg))$cfg=[];
    $setupKey=(string)(getenv('MS_SETUP_KEY')?:($cfg['setup_key']??''));
    $setupAvailable=$userCount===0 && strlen($setupKey)>=32;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        $data=$action==='upload' ? $_POST : body();
        if($action==='register'){
            if($userCount!==0)fail('Veřejná registrace je vypnutá.',403);
            if(!$setupAvailable)fail('Nejprve nastav instalační klíč v konfiguraci na WebKitty.',403);
            if(!hash_equals($setupKey,(string)($data['setup_key']??'')))fail('Nesprávný instalační klíč.',403);
            $email=strtolower(val($data,'email',254)); $password=(string)($data['password']??'');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)fail('Zadej platný e-mail a heslo alespoň 12 znaků.');
            $db->beginTransaction();
            if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0){$db->rollBack();fail('Účet již existuje.');}
            $db->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
            $_SESSION['uid']=(int)$db->lastInsertId();session_regenerate_id(true); $db->commit();
            reply(['ok'=>true,'email'=>$email]);
        }
        if($action==='login'){
            $email=strtolower(val($data,'email',254));$password=(string)($data['password']??'');
            $identity=hash('sha256',$email.'|'.($_SERVER['REMOTE_ADDR']??'unknown'));
            $t=$db->prepare('SELECT failed,last_try FROM login_attempts WHERE identity=?');$t->execute([$identity]);$attempt=$t->fetch();
            if($attempt && (int)$attempt['failed']>=5 && time()-(int)$attempt['last_try']<900) fail('Příliš mnoho pokusů. Zkus to za 15 minut.',429);
            $u=$db->prepare('SELECT id,password_hash FROM users WHERE email=?');$u->execute([$email]);$account=$u->fetch();
            if(!$account||!password_verify($password,$account['password_hash'])){
                $db->prepare('INSERT INTO login_attempts(identity,failed,last_try) VALUES(?,1,?) ON CONFLICT(identity) DO UPDATE SET failed=CASE WHEN excluded.last_try-login_attempts.last_try>900 THEN 1 ELSE login_attempts.failed+1 END,last_try=excluded.last_try')->execute([$identity,time()]);
                fail('Nesprávný e-mail nebo heslo.',401);
            }
            $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$identity]);
            session_regenerate_id(true); $_SESSION['uid']=(int)$account['id']; reply(['ok'=>true]);
        }
        if($action==='logout'){$_SESSION=[];session_regenerate_id(true);reply(['ok'=>true]);}
        $uid=currentUser();
        if($action==='contact'){
            $id=(int)($data['id']??0);
            $first=val($data,'first_name',100);$last=val($data,'last_name',100);
            if($first===''||$last==='')fail('Doplň jméno a příjmení.');
            $email=val($data,'email',254);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Neplatný e-mail.');
            $stage=val($data,'stage',30);
            if(!in_array($stage,['contact','phone','meeting1','meeting2','contract','care'],true))$stage='contact';
            $months=(int)($data['care_months']??3);if(!in_array($months,[1,3,6,12],true))$months=3;
            $args=[$first,$last,val($data,'phone',70),$email,val($data,'address',400),val($data,'source',200),val($data,'notes',5000),$stage,$months,dateValue($data,'next_care')];
            if($id){if(!existsOwner($db,'contacts',$id,$uid))fail('Klient nenalezen.',404);
                $q=$db->prepare('UPDATE contacts SET first_name=?,last_name=?,phone=?,email=?,address=?,source=?,notes=?,stage=?,care_months=?,next_care=? WHERE id=? AND user_id=?');
                $q->execute([...$args,$id,$uid]);
            }else{
                $q=$db->prepare('INSERT INTO contacts(first_name,last_name,phone,email,address,source,notes,stage,care_months,next_care,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([...$args,$uid]);$id=(int)$db->lastInsertId();
            }
            reply(['ok'=>true,'id'=>$id]);
        }
        if($action==='activity'){
            $cid=(int)($data['contact_id']??0);belongs($db,$uid,$cid);
            $kind=val($data,'kind',30);
            if(!in_array($kind,['phone','meeting1','meeting2','meeting','email','care','note'],true))fail('Neplatný typ zápisu.');
            $notes=val($data,'notes',20000);
            $at=val($data,'happened_at',35)?:date('Y-m-d');
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$at))fail('Neplatné datum schůzky.');
            $db->prepare('INSERT INTO activities(user_id,contact_id,kind,happened_at,notes,outcome,next_at,transcript,review) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$uid,$cid,$kind,$at,$notes,val($data,'outcome',500),dateValue($data,'next_at'),val($data,'transcript',120000),val($data,'review',20000)]);
            $id=(int)$db->lastInsertId();
            $step=['phone'=>'phone','meeting1'=>'meeting1','meeting2'=>'meeting2','meeting'=>'meeting2','care'=>'care'];
            if(isset($step[$kind]))setStage($db,$uid,$cid,$step[$kind]);
            reply(['ok'=>true,'id'=>$id]);
        }
        if($action==='review'){
            $id=(int)($data['id']??0);if(!existsOwner($db,'activities',$id,$uid))fail('Zápis nenalezen.',404);
            $db->prepare('UPDATE activities SET transcript=?,review=? WHERE id=? AND user_id=?')->execute([val($data,'transcript',120000),val($data,'review',20000),$id,$uid]);reply(['ok'=>true]);
        }
        if($action==='contract'){
            $cid=(int)($data['contact_id']??0);belongs($db,$uid,$cid);
            $status=val($data,'status',30);if(!in_array($status,['pending','signed','cancelled'],true))fail('Neplatný stav smlouvy.');
            $product=val($data,'product',150);if(!$product)fail('Vyber produkt.');
            $commissionExpected=money($data['commission_expected']??0);
            $commissionPaid=money($data['commission_paid']??0);
            $db->prepare('INSERT INTO contracts(user_id,contact_id,product,value,commission_expected,expected_at,commission_paid,paid_at,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$cid,$product,money($data['value']??0),$commissionExpected,dateValue($data,'expected_at'),$commissionPaid,dateValue($data,'paid_at'),$status,val($data,'notes',5000)]);
            if($status==='signed')setStage($db,$uid,$cid,'contract');reply(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
        }
        if($action==='opportunity'){
            $cid=(int)($data['contact_id']??0);belongs($db,$uid,$cid);
            $status=val($data,'status',30);if(!in_array($status,['open','won','lost'],true))fail('Neplatný stav příležitosti.');
            $product=val($data,'product',150);if(!$product)fail('Zadej produkt.');
            $db->prepare('INSERT INTO opportunities(user_id,contact_id,product,kind,status,potential,expected_commission,next_at,notes) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$uid,$cid,$product,'crosssell',$status,money($data['potential']??0),money($data['expected_commission']??0),dateValue($data,'next_at'),val($data,'notes',5000)]);
            reply(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
        }
        if($action==='referral'){
            $cid=(int)($data['contact_id']??0);belongs($db,$uid,$cid);
            $name=val($data,'name',180);if(!$name)fail('Doplň jméno doporučeného.');
            $db->prepare('INSERT INTO referrals(user_id,contact_id,name,phone,permission,notes) VALUES(?,?,?,?,?,?)')->execute([$uid,$cid,$name,val($data,'phone',70),!empty($data['permission'])?1:0,val($data,'notes',5000)]);
            reply(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
        }
        if($action==='upload'){
            $aid=(int)($data['activity_id']??0);
            if(!existsOwner($db,'activities',$aid,$uid))fail('Nejdřív ulož zápis ke schůzce.',404);
            $f=$_FILES['audio']??null;
            if(!$f||!is_uploaded_file($f['tmp_name']??'')||($f['error']??1)!==UPLOAD_ERR_OK)fail('Nahrávku se nepodařilo přijmout.');
            if($f['size']<=0||$f['size']>50*1024*1024)fail('Nahrávka musí být menší než 50 MB.');
            $ext=strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['webm','mp3','m4a','mp4','ogg','wav','aac'],true))fail('Nepodporovaný formát zvuku.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
            if(!in_array($mime,['audio/webm','video/webm','audio/mpeg','audio/mp4','video/mp4','audio/ogg','application/ogg','audio/wav','audio/x-wav','audio/aac','application/octet-stream'],true))fail('Soubor nemá rozpoznaný zvukový formát.');
            $recDir=$private.'/recordings';if(!is_dir($recDir)&&!@mkdir($recDir,0700,true))fail('Chyba úložiště.',503);
            $name=bin2hex(random_bytes(20)).'.'.$ext;
            if(!move_uploaded_file($f['tmp_name'],$recDir.'/'.$name))fail('Nahrávku nelze uložit.',503);
            @chmod($recDir.'/'.$name,0600);
            $db->prepare('INSERT INTO recordings(user_id,activity_id,filename,original_name,mime,bytes) VALUES(?,?,?,?,?,?)')->execute([$uid,$aid,$name,mb_limit(basename((string)$f['name']),180),$mime,(int)$f['size']]);
            reply(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
        }
        fail('Neznámá akce.',404);
    }
    if($action==='meta'){
        $email=null;
        if(!empty($_SESSION['uid'])){$q=$db->prepare('SELECT email FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);$email=$q->fetchColumn()?:null;}
        reply(['ok'=>true,'csrf'=>$_SESSION['csrf'],'authenticated'=>(bool)$email,'email'=>$email,'setup_required'=>$userCount===0,'setup_available'=>$setupAvailable]);
    }
    $uid=currentUser();
    if($action==='state'||$action==='backup'){
        $data=[];
        foreach(['contacts','activities','contracts','opportunities','referrals','recordings'] as $table){
            $q=$db->prepare("SELECT * FROM $table WHERE user_id=? ORDER BY id DESC");$q->execute([$uid]);$data[$table]=$q->fetchAll();
        }
        foreach($data['recordings'] as &$record)unset($record['filename']);unset($record);
        reply(['ok'=>true,'data'=>$data]);
    }
    if($action==='media'){
        $id=(int)($_GET['id']??0);$q=$db->prepare('SELECT filename,mime,original_name FROM recordings WHERE id=? AND user_id=?');
        $q->execute([$id,$uid]);$record=$q->fetch();if(!$record)fail('Nahrávka nenalezena.',404);
        $path=$private.'/recordings/'.basename($record['filename']);
        if(!is_file($path))fail('Soubor neexistuje.',404);
        header('Content-Type: '.($record['mime']==='application/octet-stream'?'audio/webm':$record['mime']));
        header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="nahravka.'.pathinfo($record['filename'],PATHINFO_EXTENSION).'"');
        readfile($path);exit;
    }
    if($action==='csv'){
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="moje-schuzka-kontakty.csv"');
        echo "\xEF\xBB\xBF";$out=fopen('php://output','w');
        fputcsv($out,['Jméno','Příjmení','Telefon','E-mail','Adresa','Zdroj','Poznámky','Fáze','Péče (měsíce)','Další péče'], ';');
        $q=$db->prepare('SELECT first_name,last_name,phone,email,address,source,notes,stage,care_months,next_care FROM contacts WHERE user_id=? ORDER BY last_name');$q->execute([$uid]);
        foreach($q->fetchAll(PDO::FETCH_NUM) as $row)fputcsv($out,array_map(fn($v)=>is_string($v)&&preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$row),';');
        fclose($out);exit;
    }
    fail('Neznámá akce.',404);
} catch(Throwable $e){
    error_log('Moje schůzka: '.$e->getMessage());
    fail('Chyba serveru. Zkontroluj PHP, SQLite a oprávnění úložiště.',500);
}
