<?php
/**
 * Backend update API and DB operational settings regression contracts.
 * Run: php Backend/tests/upgrades_and_settings_contract.php
 */
$root=dirname(__DIR__,2);
$read=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
require_once $root.'/Backend/config/AppSettings.php';
require_once $root.'/Backend/config/ClientConfig.php';
$endpoint=$read('Backend/upgrades/index.php');
$rewrite=$read('Backend/upgrades/.htaccess');
$controller=$read('Backend/html/app/controllers/Settings.php');
$view=$read('Backend/html/app/views/settings/index.php');
$sidebar=$read('Backend/html/app/views/layouts/main.php');
$api=$read('Backend/webapi_config.php');
$logger=$read('Backend/html/app/services/DatabaseService.php');
$ubuntu=$read('Ubuntu/ConfigDialog.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$windows=$read('Windows/MonitorClient.cpp');
$catalog=AppSettings::catalog();
$default=ClientConfig::getConfig('ubuntu');
$checks=[
  'Endpoint serves release files outside Apache document root'=>
      str_contains($endpoint,"const MONITOR_UPDATE_RELEASE_ROOT = __DIR__.'/../../release-updates';"),
  'Endpoint serves only manifest allowlisted fixed-version packages'=>
      str_contains($endpoint,'if(!isset($allowed[$name]))upgradeError(404') &&
      str_contains($endpoint,"'manifest.json'") &&
      str_contains($endpoint,'dirname($file)!==$root'),
  'Endpoint only allows GET/HEAD and sets content type and size'=>
      str_contains($endpoint,"$".'method!=='."'GET'") &&
      str_contains($endpoint,"'Content-Length: '") &&
      str_contains($endpoint,"'X-Content-Type-Options: nosniff'"),
  'Apache rewrite sends manifest and package paths to PHP'=>
      str_contains($rewrite,'RewriteEngine On') &&
      str_contains($rewrite,'RewriteRule ^.*$ index.php [L]'),
  'Clients all use their configured backend and a fixed updates path'=>
      str_contains($ubuntu,'monitorBackendUpdateBase(getServerIP(),getServerPort())') &&
      str_contains($ubuntu,'setReadOnly(true)') &&
      str_contains($mac,'configuredBaseURL(host: String, port: String)') &&
      str_contains($mac,'updateServer.isEditable=false') &&
      str_contains($windows,'backendUpdateServer()') &&
      str_contains($windows,'WS_BORDER|ES_READONLY'),
  'Client API reads settings database'=>
      str_contains($api,'ClientConfig::getConfig($platform,$mysqli') &&
      str_contains($read('Backend/config/ClientConfig.php'),'AppSettings::values($db)'),
  'Default client schedules remain stable without DB migration'=>
      $default['intervals']['screenshot']===60 &&
      $default['intervals']['tic']===300 &&
      $default['intervals']['history']===300 &&
      $default['intervals']['keylog']===300 &&
      $default['intervals']['usb']===300,
  'OS-specific overrides are supported for all six intervals'=>
      count(array_filter(array_keys($catalog),static fn($k)=>str_starts_with($k,'platform.')))===18,
  'Known keys validate numeric range and boolean values'=>
      AppSettings::normalize('interval.screenshot.seconds','5')==='5' &&
      AppSettings::normalize('interval.screenshot.seconds','-2')===null &&
      AppSettings::normalize('interval.screenshot.seconds','999999')===null &&
      AppSettings::normalize('logging.api_requests.enabled','0')==='0' &&
      AppSettings::normalize('logging.api_requests.enabled','yes')===null,
  'Unknown keys including secrets cannot be saved'=>
      AppSettings::normalize('MONITOR_DB_PASSWORD','p')===null &&
      AppSettings::normalize('release.directory','/etc')===null,
  'Super Admin Settings page requires role and validated POST'=>
      str_contains($controller,'$this->requireSuperAdmin();') &&
      str_contains($controller,'AppSettings::normalize($key,$raw)') &&
      str_contains($controller,"'INSERT INTO app_settings") &&
      str_contains($controller,"'DELETE FROM app_settings"),
  'Settings page exposes key/value fields with CSRF and reset'=> 
      str_contains($view,'name="csrf_token"') &&
      str_contains($view,'name="key"') &&
      str_contains($view,'name="value"') &&
      str_contains($view,'settings/reset'),
  'Super Admin navigation contains Operational Settings'=>
      str_contains($sidebar,"BASE_URL ?>settings") &&
      str_contains($sidebar,'Operational Settings'),
  'API request metadata switch is enforced at logging entrypoint'=>
      str_contains($logger,"AppSettings::value(".'$this->mysqli'.",'logging.api_requests.enabled')===false"),
  'Database cleanup age uses Super Admin managed default'=>
      str_contains($read('Backend/html/app/controllers/Databasemanagement.php'),"retention.default_months") &&
      str_contains($read('Backend/html/app/views/databasemanagement/index.php'),'$defaultRetentionMonths??3'),
  'Migration creates durable key/value table and seeds safe defaults'=>
      str_contains($read('Backend/migrations/2026_11_07_application_settings.sql'),'CREATE TABLE IF NOT EXISTS app_settings') &&
      str_contains($read('Backend/migrations/2026_11_07_application_settings.sql'),'INSERT IGNORE INTO app_settings'),
];
$failed=0;
foreach($checks as $label=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$pass)++$failed;
}
echo count($checks).' release/settings checks; '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
