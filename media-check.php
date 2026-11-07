<?php
declare(strict_types=1);

// Pouze pro přihlášeného majitele: diagnostika souboru bez odeslání audia
// a bez zveřejnění jmen klientů, fyzických cest nebo obsahu záznamů.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode', '1');
session_name('moje_schuzka_sid');
session_start();

function audio_probe_reply(array $data, int $code=200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$uid=(int)($_SESSION['uid']??0);
if($uid<1)audio_probe_reply(['ok'=>false,'error'=>'Přihlas se do aplikace.'],401);
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!is_int($id))audio_probe_reply(['ok'=>false,'error'=>'Chybí platné ID nahrávky.'],400);
try {
    if(!extension_loaded('pdo_sqlite'))audio_probe_reply(['ok'=>false,'error'=>'Na serveru není PDO SQLite.'],503);
    $private=dirname(__DIR__).'/moje-schuzka-data';
    $db=new PDO('sqlite:'.$private.'/database.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $q=$db->prepare('SELECT filename,mime,bytes FROM recordings WHERE id=? AND user_id=?');
    $q->execute([$id,$uid]);
    $record=$q->fetch();
    if(!$record)audio_probe_reply(['ok'=>false,'error'=>'Nahrávka pro přihlášený účet nebyla nalezena.'],404);
    $ext=strtolower(pathinfo((string)$record['filename'],PATHINFO_EXTENSION));
    $known=['webm'=>'audio/webm','mp3'=>'audio/mpeg','m4a'=>'audio/mp4','mp4'=>'audio/mp4','ogg'=>'audio/ogg','wav'=>'audio/wav','aac'=>'audio/aac'];
    $folder=realpath($private.'/recordings');
    $file=$folder ? realpath($folder.'/'.basename((string)$record['filename'])) : false;
    if(!$folder||!$file||!str_starts_with($file,$folder.DIRECTORY_SEPARATOR)||!is_file($file)) {
        audio_probe_reply(['ok'=>true,'file_status'=>'Soubor chybí na serveru','expected_mime'=>$known[$ext]??null,'detected_mime'=>null]);
    }
    if(!is_readable($file)) {
        audio_probe_reply(['ok'=>true,'file_status'=>'Soubor nelze číst','expected_mime'=>$known[$ext]??null,'detected_mime'=>null]);
    }
    $bytes=filesize($file);
    $detected=(new finfo(FILEINFO_MIME_TYPE))->file($file);
    audio_probe_reply(['ok'=>true,
        'file_status'=>($bytes===false||$bytes===0)?'Soubor je prázdný':'Soubor existuje a lze číst',
        'expected_mime'=>$known[$ext]??null,
        'detected_mime'=>is_string($detected)?$detected:null,
        'size_matches_db'=>$bytes!==false && $bytes===(int)$record['bytes'],
    ]);
} catch(Throwable $e) {
    error_log('Audio diagnostic: '.get_class($e));
    audio_probe_reply(['ok'=>false,'error'=>'Nepodařilo se zkontrolovat úložiště.'],503);
}
