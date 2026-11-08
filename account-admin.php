<?php
declare(strict_types=1);

const SCREENS = [
    'overview'=>'Přehled', 'people'=>'Lidé', 'day'=>'Můj den', 'care'=>'Péče a doporučení',
    'results'=>'Výsledky', 'coaching'=>'Můj trénink', 'team'=>'Tým a spolupráce',
];
const ROLES = ['admin'=>'Admin', 'editor'=>'Editor', 'user'=>'Uživatel', 'viewer'=>'Pouze náhled'];
// A right also requires access to at least one of its listed screens.
const RIGHTS = [
    'contact_create'=>['Přidat kontakt',['people','team']],
    'contact_edit'=>['Upravit kontakt',['people']],
    'activity'=>['Zapsat schůzku nebo telefonát',['day']],
    'care_activity'=>['Zapsat a naplánovat péči',['care']],
    'team_activity'=>['Zapsat a naplánovat spolupráci',['team']],
    'contract'=>['Zapsat smlouvu a provizi',['results']],
    'opportunity'=>['Zapsat příležitost',['results']],
    'referral'=>['Zapsat doporučení',['care']],
    'review'=>['Uložit přepis a hodnocení',['coaching']],
    'coaching_scorecard'=>['Ohodnotit dovednosti',['coaching']],
    'practice_session'=>['Uložit nácvik',['coaching']],
    'media'=>['Přehrát nahrávku',['day']],
    'upload'=>['Přidat nahrávku',['day']],
    'import'=>['Importovat kontakty',['people']],
    'export'=>['Exportovat kontakty a dostupné zápisy',['people','results']],
    'backup'=>['Stáhnout úplnou zálohu zápisů',['people']],
];
const TEAM_STEPS = ['cooperation','recruitment','team_onboarding','team_coaching','team_checkin','team_task'];
const APPEARANCE_COLORS = ['background','menu','menu_text','button','button_text','text'];

function migrateAccounts(PDO $db): void {
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS account_profiles (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            role TEXT NOT NULL CHECK(role IN ('admin','editor','user','viewer')),
            active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
            first_name TEXT NOT NULL DEFAULT '',
            last_name TEXT NOT NULL DEFAULT '',
            phone TEXT NOT NULL DEFAULT '',
            address TEXT NOT NULL DEFAULT '',
            company TEXT NOT NULL DEFAULT '',
            position TEXT NOT NULL DEFAULT '',
            permissions TEXT NOT NULL DEFAULT '{}',
            session_version INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS password_resets (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS appearance (
            id INTEGER PRIMARY KEY CHECK(id=1),
            settings TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY,
            actor_id INTEGER,
            target_id INTEGER,
            event TEXT NOT NULL,
            detail TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        SQL);
    // The legacy application allowed one owner. Keep all legacy IDs and credentials.
    if ($db->query('SELECT 1 FROM users u LEFT JOIN account_profiles p ON p.user_id=u.id WHERE p.user_id IS NULL LIMIT 1')->fetchColumn()) {
        $db->exec("INSERT OR IGNORE INTO account_profiles(user_id,role)
            SELECT id,CASE WHEN id=(SELECT MIN(id) FROM users) THEN 'admin' ELSE 'user' END FROM users");
    }
}

function account(PDO $db, int|string $identity): ?array {
    $column = is_int($identity) ? 'id' : 'email';
    $q = $db->prepare("SELECT u.id,u.email,u.password_hash,p.* FROM users u
        JOIN account_profiles p ON p.user_id=u.id WHERE u.$column=?");
    $q->execute([$identity]);
    return $q->fetch() ?: null;
}

function permissions(array $account): array {
    $role = $account['role'];
    $overrides = json_decode($account['permissions'], true) ?: [];
    $screens = [];
    foreach (SCREENS as $id => $label) {
        $screens[$id] = $role === 'admin' || ($overrides['screens'][$id] ?? true);
    }
    $actions = [];
    $userRights = ['contact_create','activity','care_activity','team_activity','opportunity',
        'referral','review','coaching_scorecard','practice_session','media','upload'];
    foreach (RIGHTS as $id => [$label, $sections]) {
        $default = $role === 'editor' ? $id !== 'backup' : in_array($id, $userRights, true);
        $allowed = $role === 'admin' || ($role === 'viewer'
            ? $id === 'media' && ($overrides['actions'][$id] ?? true)
            : ($overrides['actions'][$id] ?? $default));
        $actions[$id] = $allowed && (bool)array_intersect($sections, array_keys(array_filter($screens)));
    }
    return ['screens'=>$screens, 'actions'=>$actions];
}

function requireRight(array $access, string $action): void {
    if (empty($access['actions'][$action])) fail('K této akci nemáš oprávnění.',403);
}

function authorizeWrite(array $access, string $action, array $data): void {
    if ($action === 'user_preferences') return;
    if ($action === 'contact') {
        $id = $data['id'] ?? 0;
        if (!is_int($id) || $id<0) fail('Neplatné ID kontaktu.');
        requireRight($access, $id ? 'contact_edit' : 'contact_create');
        if (!empty($data['import'])) requireRight($access, 'import');
        return;
    }
    if ($action === 'activity') {
        $team = ($data['team_conversation'] ?? '') === '1';
        $care = ($data['kind'] ?? '') === 'care';
        requireRight($access, $team ? 'team_activity' : ($care ? 'care_activity' : 'activity'));
        if ($team && $care) requireRight($access, 'care_activity');
        if (!empty($data['care_due']) || !empty($data['care_first_at']) || !empty($data['care_frequency']) ||
            in_array($data['next_kind'] ?? '', ['care','referral'],true) ||
            !empty($data['referral_intro']) || !empty($data['guide_care']) || !empty($data['guide_referral'])) {
            requireRight($access, 'care_activity');
        }
        if (!empty($data['collaboration_stage']) || !empty($data['guide_cooperation']) ||
            !empty($data['guide_recruitment']) || !empty($data['guide_team']) ||
            in_array($data['next_kind'] ?? '', TEAM_STEPS, true)) {
            requireRight($access, 'team_activity');
        }
        if (!empty($data['transcript']) || !empty($data['review'])) requireRight($access, 'review');
        return;
    }
    if (!isset(RIGHTS[$action]) || in_array($action,['media','import','export','backup'],true)) {
        fail('Neznámá akce.',404);
    }
    requireRight($access, $action);
}

function audit(PDO $db, string $event, ?int $actor=null, ?int $target=null, string $detail=''): void {
    $db->prepare('INSERT INTO audit_log(event,actor_id,target_id,detail) VALUES(?,?,?,?)')
        ->execute([$event,$actor,$target,$detail]);
}

function activityScope(array $activity): string {
    return ($activity['conversation_context'] ?? '') === 'team' ? 'team'
        : (($activity['kind'] ?? '') === 'care' ? 'care' : 'day');
}

function stepScope(string $kind): string {
    return in_array($kind,TEAM_STEPS,true) ? 'team'
        : (in_array($kind,['care','referral'],true) ? 'care' : 'day');
}

function activityAllowed(array $screens, array $activity): bool {
    $scope = activityScope($activity);
    return $screens[$scope] || ($scope === 'day' && $screens['coaching'] &&
        in_array($activity['kind'],['phone','meeting1','meeting2','meeting'],true));
}

function requireActivity(PDO $db, int $uid, int $id, array $access, bool $followup=false): array {
    $q = $db->prepare('SELECT a.kind,d.next_kind,g.context AS conversation_context FROM activities a
        LEFT JOIN activity_details d ON d.activity_id=a.id
        LEFT JOIN activity_conversation g ON g.activity_id=a.id WHERE a.id=? AND a.user_id=?');
    $q->execute([$id,$uid]);
    $row = $q->fetch();
    if (!$row) fail('Zápis nebyl nalezen.',404);
    $screens = $access['screens'];
    if (!activityAllowed($screens,$row) && !($followup && activityScope($row)==='day' &&
        $screens[stepScope($row['next_kind'] ?? '')])) fail('Zápis nebyl nalezen.',404);
    return $row;
}

function activityForState(array $activity, array $screens): ?array {
    $scope = activityScope($activity);
    if ($scope !== 'day' && !$screens[$scope]) return null;
    $base = ['id','contact_id','kind','happened_at','created_at'];
    $step = stepScope($activity['next_kind'] ?? '');
    if ($screens[$step] && ($screens['day'] || $step !== 'day')) {
        $base = [...$base,'next_at','next_kind','next_time','next_note','followup_activity_id'];
    } else {
        unset($activity['next_at'],$activity['next_kind'],$activity['next_time'],$activity['next_note'],$activity['followup_activity_id']);
    }
    if (!$screens['coaching']) unset($activity['transcript'],$activity['review']);
    if (!$screens['care']) {
        unset($activity['topic_notes']['care'],$activity['topic_notes']['referral'],$activity['referral_intro']);
    }
    if (!$screens['team']) {
        unset($activity['topic_notes']['cooperation'],$activity['topic_notes']['recruitment'],$activity['topic_notes']['team']);
    }
    if ($screens[$scope]) return $activity;
    if ($screens['coaching'] && activityAllowed($screens,$activity)) {
        $base = [...$base,'transcript','review'];
    } elseif (!$screens['overview'] && !$screens['results'] && !isset($activity['next_at'])) {
        return null;
    }
    return array_intersect_key($activity,array_flip($base));
}

function canBackup(array $access): bool {
    return $access['actions']['backup'] && $access['actions']['media'] &&
        !in_array(false,$access['screens'],true);
}

function publicAccount(array $row): array {
    unset($row['password_hash'], $row['session_version']);
    $row['permissions'] = json_decode($row['permissions'], true) ?: new stdClass();
    return $row;
}

function sessionAccount(PDO $db): ?array {
    if (empty($_SESSION['uid'])) return null;
    $row = account($db, (int)$_SESSION['uid']);
    $now = time();
    if (!$row || !$row['active'] || (int)$row['session_version'] !== ($_SESSION['version'] ?? null) ||
        $now - ($_SESSION['seen'] ?? 0) >= 1800 || $now - ($_SESSION['issued'] ?? 0) >= 43200) {
        $_SESSION = ['csrf'=>bin2hex(random_bytes(24))];
        return null;
    }
    $_SESSION['seen'] = $now;
    return $row;
}

function openAccountSession(array $row): void {
    $_SESSION = [];
    if (!session_regenerate_id(true)) fail('Přihlášení se nepodařilo. Zkus to znovu.',503);
    $_SESSION = ['uid'=>(int)$row['id'], 'version'=>(int)$row['session_version'],
        'seen'=>time(), 'issued'=>time(), 'csrf'=>bin2hex(random_bytes(24))];
}

function validNewPassword(mixed $password): string {
    // PHP 8.2–8.4 PASSWORD_DEFAULT uses bcrypt, which only considers the first 72 bytes.
    if (!is_string($password) || !preg_match('/^.{12,72}$/usD',$password) ||
        strlen($password)>72 || str_contains($password,"\0")) {
        fail('Heslo musí mít alespoň 12 znaků a nejvýše 72 bajtů.');
    }
    return $password;
}

function submittedEmail(array $data): string {
    $value = $data['email'] ?? '';
    if (!is_string($value) || strlen($value)>254) return '';
    return strtolower(trim($value));
}

function rateKey(string $purpose, string $identity=''): string {
    return hash('sha256', $purpose.'|'.$identity);
}

function rateLimit(PDO $db, string $key, int $limit): void {
    $q = $db->prepare('INSERT INTO login_attempts(identity,failed,last_try) VALUES(?,1,?)
        ON CONFLICT(identity) DO UPDATE SET failed=CASE WHEN excluded.last_try-login_attempts.last_try>=900
        THEN 1 ELSE login_attempts.failed+1 END,last_try=excluded.last_try
        WHERE excluded.last_try-login_attempts.last_try>=900 OR login_attempts.failed<CAST(? AS INTEGER)');
    $q->execute([$key,time(),$limit]);
    if (!$q->rowCount()) fail('Příliš mnoho pokusů. Zkus to za 15 minut.',429);
}

function resetConfig(array $cfg): array {
    $url = rtrim(trim((string)($cfg['public_url'] ?? '')), '/').'/';
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
        preg_match('/[\x00-\x20\x7f\\\\]/',$url) || !filter_var($url,FILTER_VALIDATE_URL)) $url = '';
    $from = (string)($cfg['reset_from'] ?? '');
    if (!filter_var($from,FILTER_VALIDATE_EMAIL)) $from = '';
    return [$url,$from];
}

function createReset(PDO $db, int $id): string {
    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,?)
        ON CONFLICT(user_id) DO UPDATE SET token_hash=excluded.token_hash,expires_at=excluded.expires_at')
        ->execute([$id,hash('sha256',$token),time()+1800]);
    return $token;
}

function revokeReset(PDO $db, int $id): void {
    $db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$id]);
}

function replacePassword(PDO $db, int $id, string $hash): void {
    $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash,$id]);
    $db->prepare('UPDATE account_profiles SET session_version=session_version+1 WHERE user_id=?')->execute([$id]);
    revokeReset($db,$id);
}

function handleAccountAuth(PDO $db, string $action, array $data, array $cfg, bool $setupAvailable, string $setupKey): never {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $email = submittedEmail($data);
    if ($action === 'register') {
        if (!$setupAvailable) fail('První aktivace není dostupná.',403);
        rateLimit($db, rateKey('setup',$ip), 15);
        if (!is_string($data['setup_key'] ?? null) || !hash_equals($setupKey,$data['setup_key'])) {
            fail('Nesprávný instalační klíč.',403);
        }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Zadej platný e-mail.');
        $hash = password_hash(validNewPassword($data['password'] ?? null),PASSWORD_DEFAULT);
        $db->exec('BEGIN IMMEDIATE');
        if ($db->query('SELECT 1 FROM users LIMIT 1')->fetchColumn()) fail('První účet už existuje.',409);
        $db->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)')->execute([$email,$hash]);
        $id = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO account_profiles(user_id,role) VALUES(?,'admin')")->execute([$id]);
        audit($db,'setup',$id,$id);
        $db->exec('COMMIT');
        openAccountSession(account($db,$id));
        reply(['ok'=>true]);
    }
    if ($action === 'login') {
        rateLimit($db,rateKey('login-ip',$ip),40);
        $key = rateKey('login-account',$email);
        rateLimit($db,$key,8);
        $row = account($db,$email);
        $password = $data['password'] ?? '';
        $usable = $row && $row['active'] && $row['password_hash'] !== '';
        $hash = $usable ? $row['password_hash'] : '$2y$12$.QwULkA4d9HtX0krnWd/O.FwmFF8QfZDPo3FJsGK7Co14WLhWGxtW';
        $verified = is_string($password) && strlen($password)<=4096 && !str_contains($password,"\0") &&
            password_verify($password,$hash);
        if (!$usable || !$verified) {
            audit($db,'login_failed',null,$row ? (int)$row['id'] : null);
            fail('Nesprávný e-mail nebo heslo.',401);
        }
        if (password_needs_rehash($hash,PASSWORD_DEFAULT) && strlen($password)<=72) {
            $db->prepare('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?')
                ->execute([password_hash($password,PASSWORD_DEFAULT),$row['id'],$hash]);
        }
        $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$key]);
        openAccountSession($row);
        audit($db,'login',(int)$row['id'],(int)$row['id']);
        reply(['ok'=>true]);
    }
    if ($action === 'reset_request') {
        [$url,$from] = resetConfig($cfg);
        if (!$url || !$from) fail('Obnovu hesla řeší správce.',503);
        rateLimit($db,rateKey('reset-ip',$ip),15);
        $row = account($db,$email);
        if ($row && $row['active']) {
            $db->exec('BEGIN IMMEDIATE');
            $fresh = account($db,(int)$row['id']);
            if ($fresh['active'] && $fresh['email'] === $email) {
                $q = $db->prepare('SELECT 1 FROM password_resets WHERE user_id=? AND expires_at>?');
                $q->execute([$row['id'],time()+1740]);
                if (!$q->fetchColumn()) {
                    $token = createReset($db,(int)$row['id']);
                    audit($db,'reset_requested',null,(int)$row['id']);
                }
            }
            $db->exec('COMMIT');
            if (isset($token) && !@mail($email,'Moje schuzka - obnova hesla',
                "Odkaz platí 30 minut a lze jej použít jednou.\n\n".$url.'#reset='.$token,
                ['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8'])) {
                $db->prepare('DELETE FROM password_resets WHERE token_hash=?')->execute([hash('sha256',$token)]);
                audit($db,'reset_mail_failed',null,(int)$row['id']);
            }
        }
        reply(['ok'=>true]);
    }

    rateLimit($db,rateKey('redeem-ip',$ip),25);
    $token = $data['token'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) fail('Odkaz je neplatný nebo vypršel.');
    $password = validNewPassword($data['password'] ?? null);
    $hash = password_hash($password,PASSWORD_DEFAULT);
    $db->exec('BEGIN IMMEDIATE');
    $q = $db->prepare('SELECT p.user_id,u.email FROM password_resets r JOIN account_profiles p ON p.user_id=r.user_id
        JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>? AND p.active=1');
    $q->execute([hash('sha256',$token),time()]);
    $row = $q->fetch();
    if (!$row) fail('Odkaz je neplatný nebo vypršel.');
    $id = (int)$row['user_id'];
    replacePassword($db,$id,$hash);
    $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([rateKey('login-account',$row['email'])]);
    audit($db,'password_reset',$id,$id);
    $db->exec('COMMIT');
    $_SESSION = ['csrf'=>bin2hex(random_bytes(24))];
    reply(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}

function changePassword(PDO $db, array $row, array $data): never {
    $id = (int)$row['id'];
    $key = rateKey('change-password',(string)$id);
    rateLimit($db,$key,8);
    $old = $data['current_password'] ?? '';
    if (!is_string($old) || strlen($old)>4096 || str_contains($old,"\0") || !password_verify($old,$row['password_hash'])) {
        audit($db,'password_change_failed',$id,$id);
        fail('Současné heslo nesouhlasí.',403);
    }
    $hash = password_hash(validNewPassword($data['password'] ?? null),PASSWORD_DEFAULT);
    $db->exec('BEGIN IMMEDIATE');
    $fresh = account($db,$id);
    if (!$fresh['active'] || $fresh['session_version'] !== $row['session_version'] ||
        $fresh['password_hash'] !== $row['password_hash']) fail('Účet se mezitím změnil. Přihlas se znovu.',409);
    replacePassword($db,$id,$hash);
    $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$key]);
    audit($db,'password_changed',$id,$id);
    $db->exec('COMMIT');
    openAccountSession(account($db,$id));
    reply(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}

function cleanText(mixed $value, int $max): string {
    if (!is_string($value)) fail('Zadej textovou hodnotu.');
    $value = trim($value);
    if (!preg_match('/^[^\x00-\x1f\x7f]{0,'.$max.'}$/uD',$value)) fail('Text obsahuje nepovolené znaky nebo je příliš dlouhý.');
    return $value;
}

function getAppearance(PDO $db): array {
    return json_decode($db->query('SELECT settings FROM appearance WHERE id=1')->fetchColumn() ?: '{}',true) ?: [];
}

function beginAdminWrite(PDO $db, int $actor): void {
    $db->exec('BEGIN IMMEDIATE');
    $row = account($db,$actor);
    if (!$row || !$row['active'] || $row['role'] !== 'admin' ||
        (int)$row['session_version'] !== ($_SESSION['version'] ?? null)) {
        fail('Přihlas se znovu jako správce.',401);
    }
}

function saveAppearance(PDO $db, int $actor, array $data): never {
    $colors = $data['colors'] ?? null;
    $labels = $data['labels'] ?? null;
    $size = $data['font_size'] ?? null;
    if (!is_array($colors) || !is_array($labels) || array_diff(array_keys($colors),APPEARANCE_COLORS) ||
        array_diff_key($labels,SCREENS) || !is_int($size) || $size<85 || $size>130) fail('Neplatné nastavení vzhledu.');
    foreach ($colors as $color) {
        if (!is_string($color) || !preg_match('/^#[a-f0-9]{6}$/iD',$color)) fail('Neplatná barva.');
    }
    foreach ($labels as &$label) $label = cleanText($label,32);
    unset($label);
    $settings = ['colors'=>$colors,'labels'=>$labels,'font_size'=>$size];
    beginAdminWrite($db,$actor);
    $db->prepare('INSERT INTO appearance(id,settings) VALUES(1,?)
        ON CONFLICT(id) DO UPDATE SET settings=excluded.settings')->execute([json_encode($settings,JSON_THROW_ON_ERROR)]);
    audit($db,'appearance_saved',$actor);
    $db->exec('COMMIT');
    reply(['ok'=>true,'appearance'=>$settings]);
}

function saveAccount(PDO $db, int $actor, array $data): never {
    $id = $data['id'] ?? 0;
    $role = $data['role'] ?? '';
    $active = $data['active'] ?? null;
    $email = submittedEmail($data);
    if (!is_int($id) || $id<0 || !is_string($role) || !isset(ROLES[$role]) ||
        !is_bool($active) || !filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Zkontroluj e-mail, roli a stav účtu.');
    $overrides = [];
    foreach (['screens'=>SCREENS,'actions'=>RIGHTS] as $group=>$allowed) {
        $values = $data['permissions'][$group] ?? [];
        if (!is_array($values)) fail('Neplatný seznam oprávnění.');
        foreach ($values as $key=>$value) {
            if (!isset($allowed[$key]) || !is_bool($value)) fail('Neplatné oprávnění.');
            $overrides[$group][$key] = $value;
        }
    }
    $values = [];
    foreach (['first_name'=>80,'last_name'=>80,'phone'=>70,'address'=>400,'company'=>160,'position'=>100] as $field=>$max) {
        $values[] = cleanText($data[$field] ?? '',$max);
    }
    beginAdminWrite($db,$actor);
    $byEmail = account($db,$email);
    if ($byEmail && (int)$byEmail['id'] !== $id) fail('Tento e-mail už používá jiný účet.',409);
    $previous = $id ? account($db,$id) : null;
    $changedAccess = false;
    if ($id) {
        if (!$previous) fail('Účet nebyl nalezen.',404);
        if ($id === $actor && (!$active || $role !== 'admin')) fail('Vlastní účet správce musí zůstat aktivní.');
        if ($previous['role'] === 'admin' && $previous['active'] && (!$active || $role !== 'admin') &&
            (int)$db->query("SELECT COUNT(*) FROM account_profiles WHERE role='admin' AND active=1")->fetchColumn() <= 1) {
            fail('Musí zůstat alespoň jeden aktivní správce.',409);
        }
        $db->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email,$id]);
        $changedAccess = $previous['email'] !== $email || $previous['role'] !== $role ||
            (bool)$previous['active'] !== $active || (json_decode($previous['permissions'],true) ?: []) != $overrides;
    } else {
        $db->prepare("INSERT INTO users(email,password_hash) VALUES(?,'')")->execute([$email]);
        $id = (int)$db->lastInsertId();
    }
    $db->prepare('INSERT INTO account_profiles(user_id,role,active,first_name,last_name,phone,address,company,position,permissions)
        VALUES(?,?,?,?,?,?,?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET role=excluded.role,active=excluded.active,
        first_name=excluded.first_name,last_name=excluded.last_name,phone=excluded.phone,address=excluded.address,
        company=excluded.company,position=excluded.position,permissions=excluded.permissions,
        session_version=account_profiles.session_version+?')
        ->execute([$id,$role,(int)$active,...$values,json_encode((object)$overrides,JSON_THROW_ON_ERROR),(int)$changedAccess]);
    if ($changedAccess) revokeReset($db,$id);
    audit($db,$previous ? 'account_updated' : 'account_created',$actor,$id,
        json_encode(['role'=>$role,'active'=>$active,'permissions'=>$overrides],JSON_THROW_ON_ERROR));
    $db->exec('COMMIT');
    reply(['ok'=>true,'id'=>$id,'reauthenticate'=>$id===$actor && $changedAccess]);
}

function handleAdmin(PDO $db, string $action, array $row, ?array $data, array $cfg): never {
    if ($row['role'] !== 'admin') fail('Správa je dostupná pouze správci.',403);
    $actor = (int)$row['id'];
    if ($action === 'admin_state' && $data === null) {
        $accounts = $db->query('SELECT u.id,u.email,p.* FROM users u
            JOIN account_profiles p ON p.user_id=u.id ORDER BY u.email')->fetchAll();
        $log = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 150')->fetchAll();
        reply(['ok'=>true,'accounts'=>array_map('publicAccount',$accounts),'audit'=>$log,
            'roles'=>ROLES,'screens'=>SCREENS,'rights'=>array_map(fn($right)=>$right[0],RIGHTS)]);
    }
    if ($data === null) fail('Použij POST.',405);
    if ($action === 'admin_appearance') saveAppearance($db,$actor,$data);
    if ($action === 'admin_account') saveAccount($db,$actor,$data);
    if ($action === 'admin_reset') {
        $id = $data['id'] ?? 0;
        if (!is_int($id) || $id<1) fail('Neplatný účet.');
        beginAdminWrite($db,$actor);
        $target = account($db,$id);
        if (!$target || !$target['active']) fail('Účet musí být aktivní.',409);
        $token = createReset($db,$id);
        audit($db,'reset_issued',$actor,$id);
        $db->exec('COMMIT');
        [$url] = resetConfig($cfg);
        reply(['ok'=>true,'reset'=>($url ?: './').'#reset='.$token]);
    }
    fail('Neznámá akce.',404);
}
