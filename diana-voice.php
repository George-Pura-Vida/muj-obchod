<?php
declare(strict_types=1);

function dianaVoiceAllowed(array $access): bool {
    return $access['actions']['activity'] || $access['actions']['care_activity'] || $access['actions']['team_activity'];
}

function dianaVoiceKey(array $cfg): string {
    return trim((string)(getenv('DIANA_OPENAI_API_KEY') ?: ($cfg['diana_openai_api_key'] ?? '')));
}

function dianaVoice(PDO $db, int $uid, array $access, array $cfg, string $action): never {
    if (!dianaVoiceAllowed($access)) fail('K hlasovému zápisu nemáš oprávnění.',403);
    $key=dianaVoiceKey($cfg);
    if ($key==='' || !extension_loaded('curl')) fail('Hlas Diany není na serveru nastaven.',503);

    $transcribe=$action==='diana_transcribe';
    $headers=['Authorization: Bearer '.$key];
    if ($transcribe) {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>2*1024*1024) fail('Diktát je příliš velký.',413);
        $audio=$_FILES['audio'] ?? [];
        if (($audio['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
            !is_uploaded_file($audio['tmp_name']) || $audio['size']<1 || $audio['size']>1900000) {
            fail('Nahraj krátký hlasový diktát do 1,9 MB.',422);
        }
        $type=(new finfo(FILEINFO_MIME_TYPE))->file($audio['tmp_name']);
        $extension=match($type) {
            'audio/webm','video/webm'=>'webm',
            'audio/ogg','application/ogg'=>'ogg',
            'audio/mp4','video/mp4','audio/x-m4a'=>'mp4',
            default=>null
        };
        if ($extension===null) fail('Tento formát nahrávky prohlížeče není podporován.',422);
        $url='https://api.openai.com/v1/audio/transcriptions';
        $body=[
            'model'=>'gpt-4o-mini-transcribe',
            'language'=>'cs',
            'file'=>new CURLFile($audio['tmp_name'], $type, 'diktat.'.$extension),
        ];
    } else {
        $url='https://api.openai.com/v1/audio/speech';
        $headers[]='Content-Type: application/json';
        $body=json_encode([
            'model'=>'gpt-4o-mini-tts',
            'voice'=>'coral',
            'input'=>'Hotovo. Zápis je uložený v Mém obchodu.',
            'instructions'=>'Mluv česky, klidně a přirozeně.',
            'response_format'=>'mp3',
        ],JSON_THROW_ON_ERROR);
    }

    $db->exec('CREATE TABLE IF NOT EXISTS diana_voice_usage (
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        day TEXT NOT NULL, requests INTEGER NOT NULL)');
    $limit=$db->prepare('INSERT INTO diana_voice_usage(user_id,day,requests) VALUES (?,?,1)
        ON CONFLICT(user_id) DO UPDATE SET day=excluded.day,
        requests=CASE WHEN diana_voice_usage.day=excluded.day THEN requests+1 ELSE 1 END
        WHERE diana_voice_usage.day<>excluded.day OR requests<60');
    $limit->execute([$uid,gmdate('Y-m-d')]);
    if (!$limit->rowCount()) fail('Denní limit hlasových požadavků byl vyčerpán.',429);
    session_write_close();

    $curl=curl_init($url);
    curl_setopt_array($curl,[
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>40, CURLOPT_RETURNTRANSFER=>true,
    ]);
    $result=curl_exec($curl);
    $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    if (!is_string($result) || $status!==200) fail('Hlasová služba je teď nedostupná. Zkus to znovu.',502);

    if (!$transcribe) reply(['ok'=>true,'audio'=>base64_encode($result)]);
    $text=json_decode($result,true)['text'] ?? '';
    if (!is_string($text) || trim($text)==='') fail('V nahrávce se nepodařilo rozpoznat řeč.',422);
    reply(['ok'=>true,'text'=>trim($text)]);
}
