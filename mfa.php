<?php
declare(strict_types=1);
require __DIR__.'/account-admin.php';
ini_set('display_errors','0');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=in_array(strtolower((string)($_SERVER['HTTPS']??'')),['on','1'],true);
$local=PHP_SAPI==='cli-server'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
if(!$secure&&!$local){http_response_code(403);exit('Použijte HTTPS.');}
session_name('moje_schuzka_sid');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
if(!session_start()){http_response_code(503);exit;}
function fail(string $message,int $status=400): never {http_response_code($status);exit($message);}
try{
$db=new PDO('sqlite:'.dirname(__DIR__).'/moje-schuzka-data/database.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON');
$mfaName='Můj obchod';$mfaOrigin='https://muj-obchod.jirijanousek.cz';$mfaLogin='/';
$mfaFind=static function(int $id)use($db):?array{$u=$id>0?account($db,$id):null;return $u&&!empty($u['active'])?$u:null;};
$mfaOpen=static function(array $u):void{openAccountSession($u,true);};
require __DIR__.'/mfa-page.php';
}catch(Throwable $e){http_response_code(503);error_log('MFA bootstrap failed: '.get_class($e));exit('Ověření je dočasně nedostupné.');}
