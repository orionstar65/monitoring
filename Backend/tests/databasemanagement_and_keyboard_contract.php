<?php
// php Backend/tests/databasemanagement_and_keyboard_contract.php
$root=dirname(__DIR__,2);
$get=static fn(string $path)=>file_get_contents($root.'/'.$path);
$db=$get('Backend/html/app/controllers/Databasemanagement.php');
$view=$get('Backend/html/app/views/databasemanagement/index.php');
$layout=$get('Backend/html/app/views/layouts/main.php');
$events=$get('Backend/eventhandler.php');
$service=$get('Backend/html/app/services/DatabaseService.php');
$identity=$get('Backend/services/ClientIdentity.php');
$ubuntu=$get('Ubuntu/MonitorClient.cpp');
$mac=$get('Mac/MonitorClient/AppDelegate.swift');
$shared=$get('Backend/html/app/views/view/partials/key_history_shared.php');
$checks=[
    'Database management constructor requires Super Admin'=>
        str_contains($db,'$this->requireSuperAdmin();'),
    'Database status derives actual tables and sizes from information_schema'=>
        str_contains($db,'information_schema.TABLES') &&
        str_contains($db,'t.DATA_LENGTH,t.INDEX_LENGTH') &&
        str_contains($view,'Estimated rows'),
    'Cleanup never deletes identity, settings or screenshot metadata'=>
        str_contains($db,"private const CLEANABLE = ['browser_history_logs','key_logs','usb_device_logs']")
        &&str_contains($db,'WHERE created_at < :cutoff'),
    'Cleanup uses a bounded transactional deletion and updates stats'=>
        str_contains($db,'private const BATCH_SIZE = 2000') &&
        str_contains($db,'FOR UPDATE') &&
        str_contains($db,'$pdo->beginTransaction();') &&
        str_contains($db,'UPDATE storage_statistics'),
    'Cleanup requires confirmation and a bounded month input'=>
        str_contains($db,"'DELETE OLD LOGS'") &&
        str_contains($view,'id="dbRetentionMonths"') &&
        str_contains($view,'value="3"'),
    'Backup and restore require explicit Super Admin POST'=>
        str_contains($db,'public function download_backup()') &&
        str_contains($db,'public function restore_backup()') &&
        str_contains($db,'$this->requirePost();'),
    'Backup streams a gzip SQL artifact outside the web root'=>
        str_contains($db,"'mysqldump'") &&
        str_contains($db,'gzopen(') &&
        str_contains($db,'sys_get_temp_dir()'),
    'Restore validates backup marker, typed confirmation and recent backup'=>
        str_contains($db,'-- MonitorClient DB Backup v1 database=') &&
        str_contains($db,"'RESTORE DATABASE'") &&
        str_contains($db,'monitor_backup_at'),
    'DB link belongs to the protected Super Admin sidebar section'=>
        str_contains($layout,'href="<?= BASE_URL ?>databasemanagement"')
        &&str_contains($layout,'<?php if($privileged): ?>'),
    'Ubuntu scheduled keyboard uploads are connected'=>
        str_contains($ubuntu,'connect(this, &MonitorClient::keyLog, this, &MonitorClient::sendKeyLogs);'),
    'macOS waits for a full keyboard batch acknowledgement'=>
        str_contains($mac,'keyboardUploadInProgress') &&
        str_contains($mac,'inserted==batch.count') &&
        str_contains($mac,'self.keyLogs.removeFirst(batch.count)'),
    'Backend key batches are transactional and do not acknowledge zero inserts'=>
        str_contains($service,'$this->mysqli->begin_transaction()') &&
        str_contains($events,'No valid keyboard records in batch'),
    'Legacy IP match still requires one LAN assigned monitor'=>
        str_contains($identity,'monitoringIsPrivateLanAddress($peer)') &&
        str_contains($identity,'count($rows)!==1') &&
        str_contains($identity,'m.last_seen_ip=?'),
    'Transcript groups applications and condenses contiguous special keys'=>
        str_contains($shared,'key-transcript-app-row') &&
        str_contains($shared,'previous.repeat') &&
        str_contains($shared,'seenIds.has(eventId)'),
];
$failures=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failures++;
}
echo count($checks).' checks; '.$failures.' failed'.PHP_EOL;
exit($failures?1:0);
