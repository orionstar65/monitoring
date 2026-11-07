<?php
declare(strict_types=1);

/**
 * Public, read-only update distribution endpoint.
 * Apache DocumentRoot is Backend/; the release files live outside it at
 * repository-root release-updates/. Never expose arbitrary filesystem paths.
 *
 * GET /upgrades/                  => manifest.json
 * GET /upgrades/manifest.json     => manifest.json
 * GET /upgrades/MonitorClient-... => allowlisted, versioned artifact
 */
const MONITOR_UPDATE_RELEASE_ROOT = __DIR__.'/../../release-updates';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method!=='GET' && $method!=='HEAD'){
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
function upgradeError(int $code,string $message):void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$message]);
    exit;
}
$uriPath=(string)parse_url((string)($_SERVER['REQUEST_URI']??'/upgrades/'),PHP_URL_PATH);
if(!preg_match('~(?:^|/)upgrades(?:/(.*))?$~D',$uriPath,$parts)){
    upgradeError(404,'Unknown update route');
}
$name=$parts[1]??'';
if($name==='' || $name==='index.php')$name='manifest.json';
// Reject traversal, encoding ambiguity, backslashes, and unversioned files.
if($name!==basename($name) || !preg_match('/^[A-Za-z0-9._-]{1,180}$/D',$name)
    || str_contains($name,'..') || str_contains($name,'\\')){
    upgradeError(404,'Unknown update artifact');
}
$root=realpath(MONITOR_UPDATE_RELEASE_ROOT);
if($root===false || !is_dir($root))upgradeError(503,'Update repository unavailable');
$manifestPath=$root.'/manifest.json';
if(!is_file($manifestPath) || filesize($manifestPath)>262144){
    upgradeError(503,'Update manifest unavailable');
}
$manifestRaw=file_get_contents($manifestPath);
if($manifestRaw===false)upgradeError(503,'Update manifest unavailable');
$manifest=json_decode($manifestRaw,true);
if(!is_array($manifest) || ($manifest['schema']??null)!==1
    || !is_array($manifest['platforms']??null)){
    upgradeError(503,'Update manifest invalid');
}
$allowed=[];
foreach(['windows-x64'=>'zip','macos-arm64'=>'pkg','macos-x64'=>'pkg','ubuntu-amd64'=>'deb'] as $platform=>$ext){
    $row=$manifest['platforms'][$platform]??null;
    if(!is_array($row))continue;
    $version=$row['version']??'';
    $file=$row['file']??'';
    if(!is_string($version)||!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D',$version))continue;
    if($file==='MonitorClient-'.$version.'-'.$platform.'.'.$ext
        && is_string($row['sha256']??null)
        && preg_match('/^[a-f0-9]{64}$/iD',$row['sha256'])
        && is_numeric($row['size']??null) && (int)$row['size']>0
        && (int)$row['size']<=1073741824){
        $allowed[$file]=['type'=>match($ext){
            'deb'=>'application/vnd.debian.binary-package',
            'pkg'=>'application/octet-stream',
            'zip'=>'application/zip'
        },'size'=>(int)$row['size']];
    }
}
if($name==='manifest.json'){
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: '.strlen($manifestRaw));
    if($method==='GET')echo $manifestRaw;
    exit;
}
if(!isset($allowed[$name]))upgradeError(404,'Update package not published');
$file=realpath($root.'/'.$name);
if($file===false || dirname($file)!==$root || !is_file($file)){
    upgradeError(404,'Update artifact missing');
}
$size=filesize($file);
if($size===false || $size!==$allowed[$name]['size']){
    upgradeError(503,'Update artifact not ready');
}
header('Content-Type: '.$allowed[$name]['type']);
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.$size);
header('Cache-Control: public, max-age=300');
if($method==='HEAD')exit;
$stream=fopen($file,'rb');
if(!$stream)upgradeError(503,'Cannot read update artifact');
fpassthru($stream);
fclose($stream);
