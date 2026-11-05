<?php
// Source-level regression contract: php Backend/tests/password_signin_settings_v15_contract.php
// No credentials, database connection, or network access needed.
$root=dirname(__DIR__,2);
$read=static function(string $path)use($root):string{
    $content=file_get_contents($root.'/'.$path);
    if($content===false)throw new RuntimeException('Missing '.$path);
    return $content;
};
$api=$read('Backend/client_auth.php');
$identity=$read('Backend/services/ClientIdentity.php');
$win=$read('Windows/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$linux=$read('Ubuntu/ConfigDialog.cpp');
$linuxHeader=$read('Ubuntu/ConfigDialog.h');
$linuxClient=$read('Ubuntu/MonitorClient.cpp');
$checks=[
 'Desktop password login action'=>str_contains($api,"if(\$action==='login')"),
 'Password verified server-side'=>str_contains($api,'password_verify($password'),
 'Temporary passwords require web change'=>str_contains($api,"'password_change_required'"),
 'Login failures rate limited'=>str_contains($api,'Too many login attempts. Try again later.'),
 'Signed-in session has no automatic expiration'=>str_contains($api,"VALUES (?,?,NULL,'device',NULL)"),
 'Raw token is not stored in session table'=>str_contains($api,"\$hash=hash('sha256',\$token)"),
 'Sign out revokes server session'=>str_contains($api,'UPDATE client_sessions SET revoked_at=NOW() WHERE id=? AND user_id=?'),
 'Existing session restores assigned monitors'=>str_contains($api,"if(\$action==='restore')") && str_contains($api,"'monitors'=>\$monitors"),
 'First signed-in monitor can be created'=>str_contains($api,'if($currentId>0 && (!$current') && str_contains($api,'if($mid>0 && (!$origin'),
 'HTTP still requires trusted LAN opt-in'=>str_contains($identity,'MONITOR_ALLOW_HTTP'),
 'Windows User No input'=>str_contains($win,'SETTINGS_SIGNIN_USER'),
 'Windows password input is masked'=>str_contains($win,'ES_PASSWORD|ES_AUTOHSCROLL'),
 'Windows Sign In sends password'=>str_contains($win,'req["action"]="login"') && str_contains($win,'req["password"]=password'),
 'Windows Sign Out in settings'=>str_contains($win,'"Sign Out",WS_TABSTOP'),
 'Windows Session saved in credential manager'=>str_contains($win,'rememberClientToken(token)'),
 'Windows Monitor selection waits for Save'=>str_contains($win,'Monitor selection is a draft until Save'),
 'Windows has manual actions in Settings'=>str_contains($win,'SETTINGS_SEND_ALL') && str_contains($win,'SETTINGS_REFRESH_CONFIG'),
 'Windows manual tray items removed'=>!str_contains($win,'AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_SEND_ALL') && !str_contains($win,'AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_CONFIG_REFRESH'),
 'macOS secure password field'=>str_contains($mac,'let passwordField = NSSecureTextField()'),
 'macOS Sign In uses User No password'=>str_contains($mac,'self.clientAuthRequest(["action":"login","user_no":number,"password":password])'),
 'macOS token stored in keychain'=>str_contains($mac,'self.keychainSaveToken(token)'),
 'macOS monitor selection staged until Save'=>str_contains($mac,'Choosing a monitor only updates the draft UI'),
 'macOS Settings has manual commands'=>str_contains($mac,'case "send-all":') && str_contains($mac,'case "grant-permissions":'),
 'macOS Sign Out action retained'=>str_contains($mac,'case "disconnect":'),
 'Ubuntu masked password field'=>str_contains($linux,'passwordInput->setEchoMode(QLineEdit::Password)'),
 'Ubuntu sign-in action wired'=>str_contains($linux,'&ConfigDialog::onSignInClicked'),
 'Ubuntu Sign Out implemented'=>str_contains($linux,'void ConfigDialog::onLogoutClicked()'),
 'Ubuntu token stored in secret store'=>str_contains($linux,'rememberToken(token)'),
 'Ubuntu manual actions emitted in Settings'=>str_contains($linuxHeader,'sendAllRequested()') && str_contains($linuxHeader,'refreshConfigurationRequested()'),
 'Ubuntu manual actions wired to client'=>str_contains($linuxClient,'&ConfigDialog::sendAllRequested') && str_contains($linuxClient,'&ConfigDialog::refreshConfigurationRequested'),
 'Ubuntu monitor selection staged until Save'=>str_contains($linux,'Choosing a monitor only previews its details. Save commits selection'),
 'Ubuntu old user section hidden'=>str_contains($linux,'userGroup->setVisible(false)'),
];
$failed=0;
foreach($checks as $name=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$pass)$failed++;
}
exit($failed?1:0);
