<?php
/**
 * Read-only CLI media diagnostic for screenshot routing.
 * php Backend/diagnose_screenshot_media.php --monitor-id=1046
 * php Backend/diagnose_screenshot_media.php --path='data/thumbnails/id_1046/...jpg'
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('BASEPATH',__DIR__.'/html/');
require_once __DIR__.'/html/app/services/MonitorMediaPaths.php';

$monitorId=null;$directPath=null;
foreach($argv as $arg){
    if(str_starts_with($arg,'--monitor-id=')){
        $monitorId=filter_var(substr($arg,13),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    }
    if(str_starts_with($arg,'--path='))$directPath=substr($arg,7);
}
if($monitorId===null && $directPath===null){
    fwrite(STDERR,"Usage: php Backend/diagnose_screenshot_media.php --monitor-id=1046 | --path=data/thumbnails/id_1046/date/file.jpg\n");
    exit(2);
}

echo "PHP user: ".(function_exists('posix_geteuid')?posix_geteuid():'unknown').PHP_EOL;
echo "Storage roots:".PHP_EOL;
foreach(monitorMediaRoots() as $root)echo "  $root".PHP_EOL;

$failures=0;
$inspect=static function(string $label,?string $stored)use(&$failures):void{
    if($stored===null||$stored===''){
        echo "$label: (none)".PHP_EOL;return;
    }
    $canonical=monitorPublicMediaPath($stored);
    $resolved=monitorResolveMediaPath($stored);
    echo "$label: ".($canonical??'[invalid canonical path]').PHP_EOL;
    echo "  ".($resolved?"FOUND readable file: $resolved":"MISSING or unreadable").PHP_EOL;
    if(!$resolved){
        $failures++;
        if($canonical!==null){
            $relative=substr($canonical,strlen('data/'));
            foreach(monitorMediaRoots() as $root)
                echo "  Candidate: $root/$relative".PHP_EOL;
        }
    }
};
if($directPath!==null)$inspect('URL path',$directPath);
if($monitorId!==null){
    require_once __DIR__.'/services/ClientIdentity.php';
    try{
        $db=clientIdentityDatabase();
        $st=$db->prepare('SELECT id,file_path,thumbnail_path,capture_datetime FROM screenshot_metadata WHERE monitor_ip_id=? ORDER BY capture_datetime DESC,id DESC LIMIT 10');
        $st->bind_param('i',$monitorId);$st->execute();
        $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();$db->close();
        echo "Monitor #$monitorId: ".count($rows)." latest screenshot metadata rows inspected".PHP_EOL;
        foreach($rows as $row){
            echo "Screenshot row #".$row['id']." (".$row['capture_datetime'].")".PHP_EOL;
            $inspect('  original',$row['file_path']);
            $inspect('  thumbnail',$row['thumbnail_path']);
        }
        if(!$rows)echo "No screenshot_metadata rows for this monitor ID. Check the ingestion identity and database.\n";
    }catch(Throwable $e){
        fwrite(STDERR,"Database query failed: ".$e->getMessage()."\n");exit(2);
    }
}
echo $failures?"Investigate the missing/unreadable media paths above.\n":"Paths resolved successfully. If browser still fails, check response status and Apache logs.\n";
exit($failures?1:0);
