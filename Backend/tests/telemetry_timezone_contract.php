<?php
// Run from the repository root: php Backend/tests/telemetry_timezone_contract.php
require_once dirname(__DIR__).'/services/TelemetryTime.php';

$root=dirname(__DIR__,2);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$upload=$read('Backend/webapi.php');
$view=$read('Backend/html/app/controllers/View.php');
$logs=$read('Backend/html/app/controllers/Logbrowse.php');
$browser=$read('Backend/html/app/views/view/index.php');
$logUI=$read('Backend/html/app/views/view/logbrowse.php');
$ubuntu=$read('Ubuntu/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$windows=$read('Windows/MonitorClient.cpp');

$chicago=new DateTimeZone('America/Chicago');
$midnight=static fn(string $day):DateTimeImmutable=>
    new DateTimeImmutable($day.' 00:00:00',$chicago);
$start=$midnight('2026-11-06');
$end=$midnight('2026-11-07');
$novStart=$midnight('2026-11-01');
$novEnd=$midnight('2026-11-02');
$marStart=$midnight('2026-03-08');
$marEnd=$midnight('2026-03-09');
$utcBounds=monitorUtcBounds([
    'start_date'=>'2026-11-06',
    'end_date'=>'2026-11-06',
    'start_utc'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
    'end_utc'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z')
]);
$legacyBounds=monitorUtcBounds(['start_date'=>'2026-11-06','end_date'=>'2026-11-06']);
$legacyTimezone=date_default_timezone_get();
date_default_timezone_set('Pacific/Honolulu');
try {
    $legacy=monitorCaptureTimeUTC('2026-11-07 01:00:00');
} finally {
    date_default_timezone_set($legacyTimezone);
}
$invalid=static function(array $params):bool {
    try{monitorUtcBounds($params);return false;}
    catch(InvalidArgumentException $error){return true;}
};
$checks=[
 'Explicit -06:00 capture becomes correct UTC date'=>
    monitorCaptureTimeUTC('2026-11-06T19:00:00-06:00')?->format('Y-m-d H:i:s')
      ==='2026-11-07 01:00:00',
 'Z and other offset forms parse as the same absolute instant'=>
    monitorCaptureTimeUTC('2026-11-07T01:00:00Z')?->getTimestamp()===
    monitorCaptureTimeUTC('2026-11-07T06:30:00+05:30')?->getTimestamp(),
 'Invalid calendar dates are rejected rather than rolled forward'=>
    monitorCaptureTimeUTC('2026-02-30T10:30:00Z')===null,
 'Legacy timestamp interpretation is independent of PHP default timezone'=>
    $legacy?->format('Y-m-d H:i:s')==='2026-11-07 01:00:00',
 'Chicago November 6 local calendar resolves to UTC Nov 6 06:00 through Nov 7 06:00'=>
    $utcBounds===['2026-11-06 06:00:00','2026-11-07 06:00:00'],
 'Backward-compatible date-only bounds are UTC and end exclusive'=>
    $legacyBounds===['2026-11-06 00:00:00','2026-11-07 00:00:00'],
 'DST spring day is 23 hours, not a fixed 24-hour window'=>
    ($marEnd->getTimestamp()-$marStart->getTimestamp())===23*3600,
 'DST autumn day is 25 hours, not a fixed 24-hour window'=>
    ($novEnd->getTimestamp()-$novStart->getTimestamp())===25*3600,
 'Reject inverted UTC ranges'=>
    $invalid(['start_utc'=>'2026-11-07T00:00:00.000Z',
              'end_utc'=>'2026-11-06T00:00:00.000Z']),
 'Reject malformed and non-UTC explicit range strings'=>
    $invalid(['start_utc'=>'2026-11-06T00:00:00-06:00']),
 'Backend screenshot files and database date derive from normalized UTC capture'=>
    str_contains($upload,'monitorCaptureTimeUTC((string)$ctime)') &&
    str_contains($upload,'$date_dir=$captureTime->format(') &&
    str_contains($upload,'$capture_datetime=$captureTime->format('),
 'All-device and single-device screenshot searches use UTC bounds'=>
    str_contains($view,'monitorUtcBounds($_GET)') &&
    str_contains($view,'$timeWhere') &&
    str_contains($view,'capture_datetime < :end_date'),
 'Screenshot timeline uses UTC time buckets, not MySQL session local time'=>
    str_contains($view,"TIMESTAMPDIFF(SECOND,'1970-01-01 00:00:00',capture_datetime)"),
 'Browser-local date filter sends separate local midnights as UTC'=>
    str_contains($browser,'localCalendarMidnightUtc(day,exclusiveNext=false)') &&
    str_contains($browser,"params.set('start_utc',from)") &&
    str_contains($browser,"params.set('end_utc',to)"),
 'Browser and keyboard history convert UTC database times to local display only'=>
    str_contains($browser,'return Date.UTC(') &&
    str_contains($logUI,'return m ? Date.UTC('),
 'Browser, keyboard and USB queries share the UTC date range helper'=>
    str_contains($logs,'monitorUtcBounds($_GET)') &&
    str_contains($logs,'key_date < :end_date') &&
    str_contains($logs,'device_date < :end_date'),
 'All three desktop clients send offset-aware screenshot capture timestamps'=>
    str_contains($ubuntu,'ctimePart.setBody(QDateTime::currentDateTime().toString(Qt::ISODate)') &&
    str_contains($mac,"formatter.dateFormat = \"yyyy-MM-dd'T'HH:mm:ssXXXXX\"") &&
    str_contains($windows,'formData += getWireDateTimeString()'),
 'Hardcoded +10 hour browser time adjustments removed'=>
    !str_contains($ubuntu,"'+10:00'") && !str_contains($mac,"'+10:00'")
];
$failed=0;
foreach($checks as $label=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$pass)$failed++;
}
echo count($checks).' timezone checks, '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
