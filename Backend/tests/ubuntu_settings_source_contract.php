<?php
// Run: php Backend/tests/ubuntu_settings_source_contract.php
// Prevents an Ubuntu regression where the Settings dialog displays the
// canonical server while background uploaders target localhost instead.
$root=dirname(__DIR__,2);
$functions=file_get_contents($root.'/Ubuntu/functions.cpp');
$client=file_get_contents($root.'/Ubuntu/MonitorClient.cpp');
$dialog=file_get_contents($root.'/Ubuntu/ConfigDialog.cpp');
$checks=[
    'Dialog and worker use the same canonical server settings file'=>
        str_contains($functions,'/monitorclient/settings.ini') &&
        str_contains($dialog,'/monitorclient/settings.ini'),
    'Worker prioritizes canonical config over working-directory INI'=>
        str_contains($functions,'QFile::exists(canonical) ? canonical : legacyPath') &&
        str_contains($functions,'QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)'),
    'Worker reads fresh disk values for every server URL resolution'=>
        str_contains($functions,'QVariant currentSetting(') &&
        str_contains($functions,'settings.sync();') &&
        str_contains($functions,'currentSetting(QStringLiteral("Server/ip"))') &&
        str_contains($functions,'currentSetting(QStringLiteral("Server/port"), 8924)'),
    'Missing address never silently resolves to localhost'=>
        !str_contains($functions,'return "127.0.0.1"') &&
        str_contains($functions,'return currentSetting(QStringLiteral("Server/ip")).toString().trimmed();'),
    'All uploaders rely on central server host and port getters'=>
        substr_count($client,'getServerIP()')>=9 &&
        substr_count($client,'getServerPort()')>=9,
    'Dialog writes only canonical settings and explicitly syncs'=>
        !str_contains($dialog,'for(const QString &path:{settingsPath(),QStringLiteral("settings.ini")})') &&
        str_contains($dialog,'QSettings cfg(settingsPath(),QSettings::IniFormat);') &&
        str_contains($dialog,'cfg.sync();'),
    'Failure to write sign-in server settings is visible'=>
        str_contains($dialog,'Server settings could not be saved')
];
$failed=0;
foreach($checks as $label=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$pass)++$failed;
}
echo count($checks).' Ubuntu server config source checks; '.$failed.' failed'.PHP_EOL;
exit($failed ? 1 : 0);
