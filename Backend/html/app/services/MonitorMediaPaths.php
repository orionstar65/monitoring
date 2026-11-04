<?php
/**
 * Canonical, read-only media resolution for authenticated dashboard routes.
 *
 * New uploads live in Backend/html/data. An older service process might have
 * written the same relative path beneath the repository's html/data. A media
 * request can resolve either one, but ONLY under approved media directories.
 * Never use a user-provided filename directly without a realpath boundary.
 */

function monitorPublicMediaPath(string $value): ?string {
    $path=str_replace('\\','/',$value);
    if($path==='' || str_contains($path,"\0"))return null;
    $path=rawurldecode($path);
    // Absolute historical paths: /.../Backend/html/data/screens/...
    $at=strpos($path,'/html/data/');
    if($at!==false)$path='data/'.substr($path,$at+strlen('/html/data/'));
    else $path=preg_replace('#^(?:\\./)?(?:html/)?data/#','data/',$path);
    $path=ltrim($path,'/');
    if(!preg_match('#^data/(screens|thumbnails|logs)/(.+)$#D',$path,$match))return null;
    // Avoid ambiguity between lexical forms and forbid path navigation.
    if(preg_match('#(^|/)(?:\\.|\\.\\.)(?:/|$)#',$path))return null;
    return $path;
}

function monitorMediaRoots(): array {
    // BASEPATH is defined by Backend/html/index.php.
    $backendHtml=realpath(BASEPATH);
    if($backendHtml===false)return [];
    $roots=[
        $backendHtml.'/data',
        dirname(dirname($backendHtml)).'/html/data', // legacy repo-root cwd
    ];
    return array_values(array_unique(array_filter(array_map('realpath',$roots))));
}

function monitorResolveMediaPath(string $source): ?string {
    $relative=monitorPublicMediaPath($source);
    if($relative===null)return null;
    $subpath=substr($relative,strlen('data/'));
    $kind=strtok($subpath,'/');
    foreach(monitorMediaRoots() as $root){
        $allowed=realpath($root.'/'.$kind);
        if($allowed===false)continue;
        $candidate=realpath($root.'/'.$subpath);
        if($candidate!==false && str_starts_with($candidate,$allowed.DIRECTORY_SEPARATOR)
           && is_file($candidate) && is_readable($candidate))return $candidate;
    }
    return null;
}
