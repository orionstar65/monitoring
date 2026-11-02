<?php
// Run: php Backend/tests/identity_guards_test.php
// Pure unit tests: no database, network, or GitHub Actions required.
require_once __DIR__.'/../services/ClientIdentity.php';

function expectIdentity(bool $condition,string $message): void {
    if(!$condition){
        fwrite(STDERR,"FAIL: ".$message.PHP_EOL);
        exit(1);
    }
}

$originalServer=$_SERVER;
$oldHttp=getenv('MONITOR_ALLOW_HTTP');
$oldProxy=getenv('MONITOR_TRUST_PROXY_HTTPS');
$oldIps=getenv('MONITOR_TRUSTED_PROXY_IPS');
try {
    putenv('MONITOR_ALLOW_HTTP=0');
    putenv('MONITOR_TRUST_PROXY_HTTPS=1');
    putenv('MONITOR_TRUSTED_PROXY_IPS=127.0.0.1,::1');
    $_SERVER['HTTP_X_FORWARDED_PROTO']='https';
    $_SERVER['REMOTE_ADDR']='8.8.8.8';
    expectIdentity(!monitoringTrustedProxyHttps(),'Must ignore spoofed proto from public IP');
    $_SERVER['REMOTE_ADDR']='192.168.10.15';
    expectIdentity(!monitoringTrustedProxyHttps(),'Must ignore spoofed proto from LAN client');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    expectIdentity(monitoringTrustedProxyHttps(),'Trusted local proxy should be accepted');
    $_SERVER['HTTP_X_FORWARDED_PROTO']='http';
    expectIdentity(!monitoringTrustedProxyHttps(),'Trusted proxy must specify HTTPS');

    putenv('MONITOR_TRUSTED_PROXY_IPS=192.168.10.2');
    $_SERVER['HTTP_X_FORWARDED_PROTO']='https';
    $_SERVER['REMOTE_ADDR']='192.168.10.2';
    expectIdentity(monitoringTrustedProxyHttps(),'Explicitly configured proxy should be accepted');
    $_SERVER['REMOTE_ADDR']='192.168.10.3';
    expectIdentity(!monitoringTrustedProxyHttps(),'Other LAN peers must not be trusted');

    $_SERVER['REMOTE_ADDR']='192.168.10.15';
    expectIdentity(!monitoringLanHttpAllowed(),'LAN HTTP must be off by default');
    putenv('MONITOR_ALLOW_HTTP=1');
    expectIdentity(monitoringLanHttpAllowed(),'Explicit opt-in must permit private LAN');
    $_SERVER['REMOTE_ADDR']='8.8.8.8';
    expectIdentity(!monitoringLanHttpAllowed(),'Public IP must not use LAN HTTP exception');

    $_SERVER['HTTP_AUTHORIZATION']='Bearer '.str_repeat('a',64);
    expectIdentity(clientBearer()===str_repeat('a',64),'Valid bearer token should parse');
    $_SERVER['HTTP_AUTHORIZATION']='Bearer '.str_repeat('X',64);
    expectIdentity(clientBearer()==='','Reject nonhex bearer tokens');
    $_SERVER['HTTP_AUTHORIZATION']='Basic '.str_repeat('a',64);
    expectIdentity(clientBearer()==='','Reject wrong Authorization scheme');
    echo "PASS: HTTPS proxy, LAN HTTP opt-in, and bearer parsing",PHP_EOL;
} finally {
    $_SERVER=$originalServer;
    foreach([
        'MONITOR_ALLOW_HTTP'=>$oldHttp,
        'MONITOR_TRUST_PROXY_HTTPS'=>$oldProxy,
        'MONITOR_TRUSTED_PROXY_IPS'=>$oldIps
    ] as $key=>$value){
        putenv($value===false ? $key : ($key.'='.$value));
    }
}
