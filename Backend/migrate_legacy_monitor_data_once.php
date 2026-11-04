<?php
/**
 * One-time, backup-first metadata alignment for legacy monitoring records.
 *
 * Usage from repository root (see MONITOR_LEGACY_MIGRATION.md):
 *   php Backend/migrate_legacy_monitor_data_once.php --dry-run
 *   php Backend/migrate_legacy_monitor_data_once.php --apply --backup=/path/to/verified.sql
 *
 * This script never moves, deletes or renames screenshot files; never guesses
 * a monitor ID from IP/MAC; never changes historical telemetry ownership.
 * Only paths to files that EXIST within the allowed data folders are updated.
 * The two old lock flags are merged into one logical setting (both mirrored).
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/services/ClientIdentity.php';

function legacyCanonicalPath(?string $stored,string $kind): ?string {
    if($stored===null||trim($stored)==='')return null;
    $value=str_replace('\\','/',$stored);
    if(str_contains($value,"\0"))return null;
    $marker='/html/data/'.$kind.'/';
    $at=strpos($value,$marker);
    if($at!==false){
        $value='data/'.$kind.'/'.substr($value,$at+strlen($marker));
    }else{
        $value=preg_replace('#^(?:\\./)?(?:html/)?data/#','data/',$value);
    }
    if(!str_starts_with($value,'data/'.$kind.'/'))return null;
    $base=realpath(__DIR__.'/html/data/'.$kind);
    $candidate=realpath(__DIR__.'/html/'.$value);
    if(!$base || !$candidate || !is_file($candidate) ||
       !str_starts_with($candidate,$base.DIRECTORY_SEPARATOR))return null;
    return $value;
}

$apply=in_array('--apply',$argv,true);
$dry=in_array('--dry-run',$argv,true);
if($apply===$dry){
    fwrite(STDERR,"Usage: php Backend/migrate_legacy_monitor_data_once.php --dry-run | --apply --backup=/path/to/verified.sql\n");
    exit(2);
}
$backup='';
foreach($argv as $arg)if(str_starts_with($arg,'--backup='))$backup=substr($arg,9);
if($apply && (!is_file($backup)||!is_readable($backup)||filesize($backup)<1024)){
    fwrite(STDERR,"Refusing to modify metadata without an existing readable, nonempty SQL backup.\n");
    exit(2);
}
$db=null;$transaction=false;
try{
    $db=clientIdentityDatabase();
    $lockId='monitor_legacy_metadata_alignment_v15_20261104';
    $db->query("CREATE TABLE IF NOT EXISTS monitoring_maintenance_runs (
        run_id VARCHAR(96) PRIMARY KEY,
        completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        users_updated INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $guard=$db->prepare('SELECT completed_at FROM monitoring_maintenance_runs WHERE run_id=?');
    $guard->bind_param('s',$lockId);$guard->execute();$prior=$guard->get_result()->fetch_assoc();$guard->close();
    if($prior){
        fwrite(STDOUT,"Already applied on ".$prior['completed_at'].". No records changed.\n");
        exit(0);
    }
    // Verify required schema: migration must run after settings_locked was added.
    $fields=[];
    $cols=$db->query('SHOW COLUMNS FROM tbl_monitor_ips');
    while($row=$cols->fetch_assoc())$fields[$row['Field']]=true;
    foreach(['id','owner_user_id','assignment_locked','settings_locked'] as $field)
        if(!isset($fields[$field]))throw new RuntimeException('Missing tbl_monitor_ips.'.$field);
    if($apply){$db->begin_transaction();$transaction=true;}
    $rows=$db->query('SELECT id,assignment_locked,settings_locked,owner_user_id FROM tbl_monitor_ips ORDER BY id');
    $lockMismatches=0;$unowned=0;
    $updateLock=$apply?$db->prepare('UPDATE tbl_monitor_ips SET assignment_locked=?,settings_locked=? WHERE id=?'):null;
    while($m=$rows->fetch_assoc()){
        if($m['owner_user_id']===null)$unowned++;
        $unified=((int)$m['assignment_locked']===1||(int)$m['settings_locked']===1)?1:0;
        if((int)$m['assignment_locked']!==$unified||(int)$m['settings_locked']!==$unified){
            $lockMismatches++;
            if($apply){
                $id=(int)$m['id'];
                $updateLock->bind_param('iii',$unified,$unified,$id);
                $updateLock->execute();
            }
        }
    }
    if($updateLock)$updateLock->close();
    $rows->free();

    $count=0;$renamed=0;$missing=0;$unchanged=0;$thumbMissing=0;
    $read=$db->query('SELECT id,file_path,thumbnail_path FROM screenshot_metadata ORDER BY id');
    $update=$apply?$db->prepare('UPDATE screenshot_metadata SET file_path=?,thumbnail_path=? WHERE id=? AND file_path=?'):null;
    while($shot=$read->fetch_assoc()){
        $count++;
        $path=legacyCanonicalPath((string)$shot['file_path'],'screens');
        $thumb=$shot['thumbnail_path']===null?null:
            legacyCanonicalPath((string)$shot['thumbnail_path'],'thumbnails');
        if($path===null){$missing++;continue;}
        if($shot['thumbnail_path']!==null && $thumb===null){
            // A missing optional thumbnail should not obstruct the screenshot.
            // Preserve the historical thumbnail reference; the viewer falls
            // back to the original screenshot when the thumbnail is missing.
            $thumbMissing++;
            $thumb=(string)$shot['thumbnail_path'];
        }
        if($path===(string)$shot['file_path']&&$thumb===$shot['thumbnail_path']){
            $unchanged++;continue;
        }
        $renamed++;
        if($apply){
            $id=(int)$shot['id'];
            $old=(string)$shot['file_path'];
            $update->bind_param('ssis',$path,$thumb,$id,$old);
            $update->execute();
            if($update->affected_rows!==1)
                throw new RuntimeException('Concurrent edit to screenshot '.$id.'; rolled back');
        }
    }
    if($update)$update->close();
    $read->free();
    if($apply){
        $mark=$db->prepare('INSERT INTO monitoring_maintenance_runs(run_id,users_updated) VALUES (?,0)');
        $mark->bind_param('s',$lockId);$mark->execute();$mark->close();
        $db->commit();$transaction=false;
    }
    fwrite(STDOUT,($apply?'APPLIED':'DRY RUN').": screenshot rows=$count, paths aligned=$renamed, unchanged=$unchanged, missing/ambiguous=$missing, missing thumbnails=$thumbMissing; locks reconciled=$lockMismatches; unowned monitors=$unowned.\n");
    fwrite(STDOUT,"No files or historical monitor ownership changed.\n");
} catch(Throwable $error){
    if($transaction&&$db instanceof mysqli)$db->rollback();
    error_log('Legacy monitor metadata migration failed: '.$error->getMessage());
    fwrite(STDERR,"Legacy migration failed: ".$error->getMessage()."\n");
    exit(1);
}finally{
    if($db instanceof mysqli)$db->close();
}
