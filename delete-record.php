<?php
declare(strict_types=1);

/** Delete only the signed-in owner's record; SQLite foreign keys remove dependent rows. */
function deleteOwnedRecord(PDO $db,int $uid,string $action,int $id,string $private): array {
    if(!in_array($action,['contact_delete','activity_delete'],true)||$id<1||$uid<1) {
        throw new DomainException('Neplatný požadavek.',400);
    }
    $table=$action==='contact_delete'?'contacts':'activities';
    $db->beginTransaction();
    try {
        $q=$db->prepare("SELECT id FROM $table WHERE id=? AND user_id=?");
        $q->execute([$id,$uid]);
        if(!$q->fetchColumn())throw new DomainException('Záznam nebyl nalezen.',404);
        $sql=$action==='contact_delete'
            ? 'SELECT r.filename FROM recordings r JOIN activities a ON a.id=r.activity_id WHERE a.contact_id=? AND a.user_id=?'
            : 'SELECT r.filename FROM recordings r WHERE r.activity_id=? AND r.user_id=?';
        $q=$db->prepare($sql);
        $q->execute([$id,$uid]);
        $files=$q->fetchAll(PDO::FETCH_COLUMN);
        $q=$db->prepare("DELETE FROM $table WHERE id=? AND user_id=?");
        $q->execute([$id,$uid]);
        if($q->rowCount()!==1)throw new DomainException('Záznam se mezitím změnil. Obnov stránku.',409);
        audit($db,$action,$uid,$uid,json_encode(['id'=>$id],JSON_THROW_ON_ERROR));
        $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
    // The database must commit before files are removed. Never follow a path outside recordings.
    $folder=realpath($private.'/recordings');
    $pending=0;
    foreach($files as $filename) {
        if(!$folder) { $pending++; continue; }
        $path=realpath($folder.'/'.basename($filename));
        if($path===false)continue;
        if(!str_starts_with($path,$folder.DIRECTORY_SEPARATOR)||!is_file($path)||!@unlink($path))$pending++;
    }
    if($pending)error_log('CRM recording cleanup pending after '.$action.' id='.$id);
    return ['ok'=>true,'id'=>$id,'cleanup_pending'=>$pending];
}
