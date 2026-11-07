<?php
/**
 * Ubuntu .deb upgrade lifecycle regression contracts.
 * Run: php Backend/tests/ubuntu_upgrade_contract.php
 *
 * Source-level assertions; native package upgrades and GUI restarts require
 * an interactive Ubuntu test host.
 */
$root=dirname(__DIR__,2);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$pack=$read('Ubuntu/create_deb_package.sh');
$helper=$read('Ubuntu/upgrade-restart.sh');
$install=$read('Ubuntu/install.sh');
$main=$read('Ubuntu/main.cpp');
$dialog=$read('Ubuntu/ConfigDialog.cpp');
$publisher=$read('scripts/publish_update.py');
$tests=[
    'Native executable exposes a headless --version check'=>
        str_contains($main,'std::strcmp(argv[1], "--version") == 0') &&
        str_contains($main,'std::printf("MonitorClient %s\n", MONITORAPP_VERSION);'),
    'Release packages compile and verify their requested version'=>
        str_contains($pack,'-DMONITORCLIENT_VERSION="$PACKAGE_VERSION"') &&
        str_contains($pack,'BUILT_VERSION="$(./build/MonitorClient --version)"') &&
        str_contains($pack,'"$BUILT_VERSION" != "MonitorClient $PACKAGE_VERSION"'),
    'Debian metadata version is verified before publishing'=>
        str_contains($pack,'dpkg-deb -f') &&
        str_contains($pack,'"$ACTUAL_PACKAGE_VERSION" != "$PACKAGE_VERSION"'),
    'Package includes upgrade helper and a version file'=>
        str_contains($pack,'cp upgrade-restart.sh') &&
        str_contains($pack,'/opt/monitorclient/upgrade-restart.sh') &&
        str_contains($pack,'/opt/monitorclient/VERSION'),
    'Package honors user-customized legacy configuration on upgrades'=>
        str_contains($pack,'DEBIAN/conffiles') &&
        str_contains($pack,'/opt/monitorclient/config/settings.ini'),
    'postinst executes restart only after configure'=>
        str_contains($pack,'if [ "${1:-}" = configure ]; then') &&
        str_contains($pack,'/opt/monitorclient/upgrade-restart.sh configure || true'),
    'Restart helper restricts to exact managed executable and outdated inode'=>
        str_contains($helper,'"$exe" == "$target"') &&
        str_contains($helper,'"$exe" == "$target (deleted)"') &&
        str_contains($helper,'"$old_inode" != "$new_inode"'),
    'Restart helper excludes root sessions and preserves desktop environment'=>
        str_contains($helper,'"$uid" -gt 0') &&
        str_contains($helper,'graphical=false') &&
        str_contains($helper,'DISPLAY=?*|WAYLAND_DISPLAY=?*') &&
        str_contains($helper,'XDG_CONFIG_HOME=?*'),
    'Restart helper does not force-kill stuck processes or launch duplicates'=>
        str_contains($helper,'kill -TERM "$pid"') &&
        !str_contains($helper,'kill -KILL') &&
        str_contains($helper,'did not exit; not launching a duplicate'),
    'Restart helper leaves user-service restarts to systemd'=>
        str_contains($helper,'grep -q \'monitorclient.service\'') &&
        str_contains($helper,'User service handles restart for UID'),
    'New process uses original unprivileged desktop session'=>
        str_contains($helper,'runuser -u "$user_name"') &&
        str_contains($helper,'/usr/bin/env -i') &&
        str_contains($helper,'setsid "$target"'),
    'GUI updater stays open if pkexec authorization is declined'=>
        str_contains($dialog,'QProcess::startDetached("pkexec"') &&
        !str_contains($dialog,'QTimer::singleShot(200,this,[](){QApplication::quit();});') &&
        str_contains($dialog,'MonitorClient remains running if'),
    'Direct install upgrades use atomic rename and same safe restart helper'=>
        str_contains($install,'sudo mv -f /opt/monitorclient/.MonitorClient.new /opt/monitorclient/MonitorClient') &&
        str_contains($install,'upgrade-restart.sh --restart-running') &&
        !str_contains($install,'sudo chown -R $USER:$USER /opt/monitorclient'),
    'Publisher refuses a changed artifact at the same version'=>
        str_contains($publisher,'increment the patch version for every new build') &&
        str_contains($publisher,'current.get("sha256") != digest.hexdigest()'),
];
$failed=0;
foreach($tests as $name=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$pass)++$failed;
}
echo count($tests).' Ubuntu upgrade source checks; '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
