<?php
// Offline media path regression tests; uses a temporary directory, no DB/network.
// Run: php Backend/tests/monitor_media_paths_test.php
if(PHP_SAPI!=='cli')exit(1);
$fixture=sys_get_temp_dir().'/monitor-media-test-'.bin2hex(random_bytes(6));
$base=$fixture.'/Backend/html';
$folder=$base.'/data/thumbnails/id_1046/2026-11-04';
if(!mkdir($folder,0700,true))throw new RuntimeException('Cannot create fixture');
$filename='1046_2026-11-04_17.22.01_fc5b5497a624df59.jpg';
$file=$folder.'/'.$filename;
file_put_contents($file,'fixture');
define('BASEPATH',$base.'/');
require_once dirname(__DIR__).'/html/app/services/MonitorMediaPaths.php';

$path='data/thumbnails/id_1046/2026-11-04/'.$filename;
$tests=[
 'URL-encoded media path normalizes correctly'=>
    monitorPublicMediaPath('data%2Fthumbnails%2Fid_1046%2F2026-11-04%2F'.$filename)===$path,
 'New absolute backend media path resolves'=>
    monitorResolveMediaPath($file)===realpath($file),
 'Current web media URL path resolves'=>
    monitorResolveMediaPath($path)===realpath($file),
 'Old relative PHP upload path resolves'=>
    monitorResolveMediaPath('./html/'.$path)===realpath($file),
 'Missing screenshot is not treated as present'=>
    monitorResolveMediaPath('data/thumbnails/id_1046/no-file.jpg')===null,
 'Traversal is rejected'=>
    monitorResolveMediaPath('data/screens/../../index.php')===null,
 'Unexpected folder is rejected'=>
    monitorPublicMediaPath('data/secrets/key.txt')===null
];
$fail=0;
foreach($tests as $label=>$pass){
    echo ($pass?'PASS ':'FAIL ').$label.PHP_EOL;
    if(!$pass)$fail++;
}
@unlink($file);
@rmdir($folder);
@rmdir(dirname($folder));
@rmdir(dirname(dirname($folder)));
@rmdir($base.'/data/thumbnails');
@rmdir($base.'/data');
@rmdir($base);
@rmdir(dirname($base));
@rmdir($fixture);
exit($fail?1:0);
