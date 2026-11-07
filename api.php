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
function collaborationStage(array $data): string {
    $stage=val($data,'collaboration_stage',30);
    if(!in_array($stage,['','potential','discussing','onboarding','partner','team','no_interest'],true))fail('Neplatná fáze spolupráce.');
    return $stage;
}
function saveCollaboration(PDO $db,int $contactId,string $stage): void {
    $db->prepare('INSERT INTO collaboration_profiles(contact_id,stage) VALUES(?,?)
        ON CONFLICT(contact_id) DO UPDATE SET stage=excluded.stage')->execute([$contactId,$stage]);
}
function nextCareDate(string $date,int $months): string {
    $from=new DateTimeImmutable($date);
    $target=$from->modify('first day of this month')->modify("+$months months");
    return $target->setDate((int)$target->format('Y'),(int)$target->format('n'),
        min((int)$from->format('j'),(int)$target->format('t')))->format('Y-m-d');
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
CREATE TABLE IF NOT EXISTS coaching_scorecards (
    activity_id INTEGER PRIMARY KEY REFERENCES activities(id) ON DELETE CASCADE,
    scores TEXT NOT NULL,
    practice TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS activity_details (
    activity_id INTEGER PRIMARY KEY REFERENCES activities(id) ON DELETE CASCADE,
    opening TEXT NOT NULL DEFAULT '',
    findings TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    result_status TEXT NOT NULL DEFAULT '',
    next_kind TEXT NOT NULL DEFAULT '',
    next_time TEXT NOT NULL DEFAULT '',
    next_note TEXT NOT NULL DEFAULT '',
    followup_activity_id INTEGER REFERENCES activities(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS activity_conversation (
    activity_id INTEGER PRIMARY KEY REFERENCES activities(id) ON DELETE CASCADE,
    topic_notes TEXT NOT NULL,
    referral_intro TEXT NOT NULL DEFAULT '',
    context TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS collaboration_profiles (
    contact_id INTEGER PRIMARY KEY REFERENCES contacts(id) ON DELETE CASCADE,
    stage TEXT NOT NULL
);
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
            $months=(int)($data['care_months']??3);if($months<1||$months>24)$months=3;
            $collaboration=array_key_exists('collaboration_stage',$data)?collaborationStage($data):null;
            $args=[$first,$last,val($data,'phone',70),$email,val($data,'address',400),val($data,'source',200),val($data,'notes',5000),$stage,$months,dateValue($data,'next_care')];
            if($id)belongs($db,$uid,$id);
            $db->beginTransaction();
            if($id){
                $q=$db->prepare('UPDATE contacts SET first_name=?,last_name=?,phone=?,email=?,address=?,source=?,notes=?,stage=?,care_months=?,next_care=? WHERE id=? AND user_id=?');
                $q->execute([...$args,$id,$uid]);
            }else{
                $q=$db->prepare('INSERT INTO contacts(first_name,last_name,phone,email,address,source,notes,stage,care_months,next_care,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([...$args,$uid]);$id=(int)$db->lastInsertId();
            }
            if($collaboration!==null)saveCollaboration($db,$id,$collaboration);
            $db->commit();
            reply(['ok'=>true,'id'=>$id]);
        }
        if($action==='activity'){
            $cid=(int)($data['contact_id']??0);belongs($db,$uid,$cid);
            $kind=val($data,'kind',30);
            if(!in_array($kind,['phone','meeting1','meeting2','meeting','email','care','note'],true))fail('Neplatný typ zápisu.');
            $notes=val($data,'notes',20000);
            $at=dateValue($data,'happened_at')??date('Y-m-d');
            $nextAt=dateValue($data,'next_at');
            $nextKind=val($data,'next_kind',30);
            $nextTime=val($data,'next_time',30);
            $nextNote=val($data,'next_note',1000);
            $status=val($data,'result_status',30);
            if(!in_array($status,['','success','open','declined'],true))fail('Neplatný výsledek aktivity.');
            if(!in_array($nextKind,['','phone','meeting','email','care','crosssell','referral','cooperation','recruitment','team_onboarding','team_coaching','team_checkin','team_task'],true))fail('Neplatný typ dalšího kroku.');
            if($nextTime!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$nextTime))fail('Neplatný čas dalšího kroku.');
            if(!$nextAt&&($nextKind!==''||$nextTime!==''||$nextNote!==''))fail('K dalšímu kroku doplň datum.');
            $from=$data['followup_from_id']??null;
            if($from!==null && !($from=filter_var($from,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])))fail('Neplatný odkaz na předchozí aktivitu.');
            $collaboration=collaborationStage($data);
            $frequency=null;
            if(($data['care_frequency']??'')!==''){
                $frequency=filter_var($data['care_frequency'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>24]]);
                if(!$frequency)fail('Frekvence péče musí být 1 až 24 měsíců.');
            }
            $firstCare=dateValue($data,'care_first_at');
            $careDue=dateValue($data,'care_due');
            if($firstCare&&($firstCare<=$at||$firstCare===$careDue))fail('Nový termín pravidelné péče musí navazovat na tuto aktivitu.');
            if($careDue&&$kind!=='care')fail('Termín pravidelné péče uzavři zápisem typu Péče.');
            if($firstCare&&$nextKind==='care'&&$nextAt===$firstCare)fail('Stejný termín ulož buď jako pravidelnou péči, nebo jako jednorázový další krok.');
            $referralIntro=val($data,'referral_intro',30);
            if(!in_array($referralIntro,['','client','introduction','joint_meeting','direct','none'],true))fail('Neplatný způsob představení.');
            $teamConversation=($data['team_conversation']??'')==='1';
            $topics=[];
            foreach(['care','crosssell','referral','cooperation','recruitment','team'] as $topic){
                if(!is_string($data['guide_'.$topic]??''))fail('Poznámka k rozhovoru musí být text.');
                $note=val($data,'guide_'.$topic,3000);
                if($note!=='')$topics[$topic]=$note;
            }
            $db->beginTransaction();
            $db->prepare('INSERT INTO activities(user_id,contact_id,kind,happened_at,notes,outcome,next_at,transcript,review) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$uid,$cid,$kind,$at,$notes,val($data,'outcome',500),$nextAt,val($data,'transcript',120000),val($data,'review',20000)]);
            $id=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO activity_details(activity_id,opening,findings,summary,result_status,next_kind,next_time,next_note) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$id,val($data,'opening',3000),val($data,'findings',10000),val($data,'summary',5000),$status,$nextKind,$nextTime,$nextNote]);
            if($topics||$referralIntro!==''||$teamConversation)$db->prepare('INSERT INTO activity_conversation(activity_id,topic_notes,referral_intro,context) VALUES(?,?,?,?)')
                ->execute([$id,json_encode((object)$topics,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),$referralIntro,$teamConversation?'team':'']);
            if($collaboration!=='')saveCollaboration($db,$cid,$collaboration);
            if($careDue){
                $q=$db->prepare('SELECT next_care,care_months FROM contacts WHERE id=? AND user_id=?');
                $q->execute([$cid,$uid]);
                $care=$q->fetch();
                if(!$care||$care['next_care']!==$careDue){
                    $db->rollBack();
                    fail('Termín péče se mezitím změnil. Obnov přehled.',409);
                }
                $firstCare??=nextCareDate(max($at,$careDue),$frequency??max(1,(int)$care['care_months']));
                if($nextKind==='care'&&$nextAt===$firstCare){
                    $db->rollBack();
                    fail('Stejný termín už vznikne jako pravidelná péče. Vyber jiný jednorázový krok.');
                }
            }
            if($frequency!==null||$firstCare!==null)$db->prepare('UPDATE contacts SET care_months=COALESCE(?,care_months),next_care=COALESCE(?,next_care) WHERE id=? AND user_id=?')
                ->execute([$frequency,$firstCare,$cid,$uid]);
            if($from!==null){
                $q=$db->prepare('INSERT INTO activity_details(activity_id,followup_activity_id)
                    SELECT id,? FROM activities WHERE id=? AND id<>? AND user_id=? AND contact_id=? AND next_at IS NOT NULL AND next_at<>\'\'
                    ON CONFLICT(activity_id) DO UPDATE SET followup_activity_id=excluded.followup_activity_id
                    WHERE activity_details.followup_activity_id IS NULL');
                $q->execute([$id,$from,$id,$uid,$cid]);
                if(!$q->rowCount()){
                    $db->rollBack();
                    fail('Předchozí úkol je už hotový nebo ho nelze najít. Obnov přehled.',409);
                }
            }
            $step=['phone'=>'phone','meeting1'=>'meeting1','meeting2'=>'meeting2','meeting'=>'meeting2','care'=>'care'];
            if(isset($step[$kind])&&!$teamConversation){
                $advance=true;
                if($from!==null){
                    $order=['contact','phone','meeting1','meeting2','contract','care'];
                    $q=$db->prepare('SELECT stage FROM contacts WHERE id=? AND user_id=?');
                    $q->execute([$cid,$uid]);
                    $advance=array_search($step[$kind],$order,true)>array_search($q->fetchColumn(),$order,true);
                }
                if($advance)setStage($db,$uid,$cid,$step[$kind]);
            }
            $db->commit();
            reply(['ok'=>true,'id'=>$id]);
        }
        if($action==='review'){
            $id=(int)($data['id']??0);if(!existsOwner($db,'activities',$id,$uid))fail('Zápis nenalezen.',404);
            $db->prepare('UPDATE activities SET transcript=?,review=? WHERE id=? AND user_id=?')->execute([val($data,'transcript',120000),val($data,'review',20000),$id,$uid]);reply(['ok'=>true]);
        }
        if($action==='coaching_scorecard'){
            $id=filter_var($data['activity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $input=$data['scores']??null;
            $skills=['opening','open_questions','narrowing','closed_questions','verification','closing'];
            if(!$id || !is_array($input) || array_diff(array_keys($input),$skills) || !is_string($data['practice']??'')){
                fail('Neplatné hodnocení schůzky.');
            }
            $scores=[];
            foreach($input as $skill=>$score){
                if(!is_int($score) || $score<1 || $score>5)fail('Skóre musí být celé číslo od 1 do 5.');
                $scores[$skill]=$score;
            }
            if(!$scores)fail('Ohodnoť alespoň jednu dovednost.');
            $q=$db->prepare("INSERT INTO coaching_scorecards(activity_id,scores,practice)
                SELECT id,?,? FROM activities
                WHERE id=? AND user_id=? AND kind IN ('phone','meeting1','meeting2','meeting')
                ON CONFLICT(activity_id) DO UPDATE SET scores=excluded.scores,practice=excluded.practice");
            $q->execute([json_encode($scores,JSON_THROW_ON_ERROR),val($data,'practice',500),$id,$uid]);
            if(!$q->rowCount())fail('Schůzka ani telefonát nebyly nalezeny.',404);
            reply(['ok'=>true]);
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
            $sql=match($table){
                'contacts'=>'SELECT c.*,p.stage AS collaboration_stage FROM contacts c LEFT JOIN collaboration_profiles p ON p.contact_id=c.id WHERE c.user_id=? ORDER BY c.id DESC',
                'activities'=>'SELECT a.*,d.opening,d.findings,d.summary,d.result_status,d.next_kind,d.next_time,d.next_note,d.followup_activity_id,g.topic_notes,g.referral_intro,g.context AS conversation_context
                    FROM activities a LEFT JOIN activity_details d ON d.activity_id=a.id
                    LEFT JOIN activity_conversation g ON g.activity_id=a.id WHERE a.user_id=? ORDER BY a.id DESC',
                default=>"SELECT * FROM $table WHERE user_id=? ORDER BY id DESC"
            };
            $q=$db->prepare($sql);$q->execute([$uid]);$data[$table]=$q->fetchAll();
        }
        foreach($data['recordings'] as &$record)unset($record['filename']);unset($record);
        foreach($data['activities'] as &$activity)$activity['topic_notes']=json_decode($activity['topic_notes']??'null',true);
        unset($activity);
        $q=$db->prepare('SELECT s.activity_id,s.scores,s.practice FROM coaching_scorecards s JOIN activities a ON a.id=s.activity_id WHERE a.user_id=?');
        $q->execute([$uid]);
        $data['scorecards']=$q->fetchAll();
        foreach($data['scorecards'] as &$card)$card['scores']=json_decode($card['scores'],true);
        unset($card);
        reply(['ok'=>true,'data'=>$data]);
    }
    // JSON fallback for hosting layers that replace binary PHP responses with HTML.
    // Each authenticated chunk is limited to 512 KiB and belongs to the session owner.
    if($action==='media_chunk'){
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!is_int($id))fail('Neplatné ID nahrávky.',400);
        $q=$db->prepare('SELECT filename FROM recordings WHERE id=? AND user_id=?');
        $q->execute([$id,$uid]);$record=$q->fetch();
        if(!$record)fail('Nahrávka nenalezena.',404);
        $folder=realpath($private.'/recordings');
        $path=$folder ? realpath($folder.'/'.basename((string)$record['filename'])) : false;
        if(!$folder||!$path||!str_starts_with($path,$folder.DIRECTORY_SEPARATOR)||!is_file($path)||!is_readable($path))
            fail('Záznam na serveru chybí nebo ho nelze načíst.',404);
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        $mimes=['webm'=>'audio/webm','mp3'=>'audio/mpeg','m4a'=>'audio/mp4','mp4'=>'audio/mp4','ogg'=>'audio/ogg','wav'=>'audio/wav','aac'=>'audio/aac'];
        if(!isset($mimes[$ext]))fail('Nepodporovaný formát.',415);
        $size=filesize($path);
        if($size===false||$size<=0||$size>50*1024*1024)fail('Neplatná velikost zvukového souboru.',422);
        $offset=filter_input(INPUT_GET,'offset',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if(!is_int($offset)||$offset>=$size)fail('Neplatná pozice čtení.',416);
        $length=min(524288,$size-$offset);
        $handle=fopen($path,'rb');
        if($handle===false)fail('Záznam nelze otevřít.',503);
        if(fseek($handle,$offset)!==0){fclose($handle);fail('Záznam nelze přečíst.',503);}
        $chunk='';
        while(strlen($chunk)<$length&&!feof($handle)){
            $part=fread($handle,$length-strlen($chunk));
            if($part===false||$part==='')break;
            $chunk.=$part;
        }
        fclose($handle);
        if(strlen($chunk)!==$length)fail('Neúplné čtení zvukového souboru.',503);
        reply(['ok'=>true,'offset'=>$offset,'next_offset'=>$offset+$length,
               'size'=>$size,'mime'=>$mimes[$ext],'bytes'=>$length,'chunk'=>base64_encode($chunk)]);
    }
    if($action==='media'){
        $id=(int)($_GET['id']??0);
        $q=$db->prepare('SELECT filename,mime,original_name FROM recordings WHERE id=? AND user_id=?');
        $q->execute([$id,$uid]);$record=$q->fetch();
        if(!$record)fail('Nahrávka nenalezena.',404);
        $path=$private.'/recordings/'.basename((string)$record['filename']);
        if(!is_file($path)||!is_readable($path))fail('Záznam na serveru chybí nebo ho nelze načíst.',404);
        $size=filesize($path);
        if($size===false||$size<=0)fail('Zvukový soubor je prázdný.',404);
        $ext=strtolower(pathinfo((string)$record['filename'],PATHINFO_EXTENSION));
        // Nepoužívej nekontrolovanou MIME hodnotu ze staršího DB záznamu.
        // Chybná metadata (např. text/html) by při přehrávání vyřadila audio.
        $knownMimes=['webm'=>'audio/webm','mp3'=>'audio/mpeg','m4a'=>'audio/mp4','mp4'=>'audio/mp4','ogg'=>'audio/ogg','wav'=>'audio/wav','aac'=>'audio/aac'];
        if(!isset($knownMimes[$ext]))fail('Nepodporovaná přípona zvukového souboru.',415);
        $mime=$knownMimes[$ext];
        $from=0;$to=$size-1;$range=(string)($_SERVER['HTTP_RANGE']??'');
        if($range!==''){
            if(!preg_match('/^bytes=(\\d*)-(\\d*)$/D',$range,$m) || ($m[1]===''&&$m[2]==='')){
                header('Content-Range: bytes */'.$size);fail('Neplatný rozsah zvukového souboru.',416);
            }
            if($m[1]===''){
                $suffix=(int)$m[2];
                if($suffix<=0){header('Content-Range: bytes */'.$size);fail('Neplatný rozsah.',416);}
                $from=max(0,$size-$suffix);
            }else{
                $from=(int)$m[1];
                if($m[2]!=='')$to=min($to,(int)$m[2]);
            }
            if($from>$to||$from>=$size){
                header('Content-Range: bytes */'.$size);fail('Požadovaný úsek neexistuje.',416);
            }
            http_response_code(206);
            header('Content-Range: bytes '.$from.'-'.$to.'/'.$size);
        }
        header('Content-Type: '.$mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: '.($to-$from+1));
        header('Content-Disposition: inline; filename="nahravka.'.$ext.'"');
        session_write_close();
        $handle=fopen($path,'rb');
        if($handle===false)exit;
        fseek($handle,$from);
        $remaining=$to-$from+1;
        while($remaining>0&&!feof($handle)){
            $bytes=fread($handle,min(65536,$remaining));
            if($bytes===false||$bytes==='')break;
            echo $bytes;$remaining-=strlen($bytes);
        }
        fclose($handle);exit;
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
