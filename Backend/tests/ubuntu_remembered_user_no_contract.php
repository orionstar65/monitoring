<?php
/**
 * Regression contracts: Ubuntu User No must render from saved metadata on
 * startup even if keyring or backend session restoration temporarily fails.
 * Run: php Backend/tests/ubuntu_remembered_user_no_contract.php
 *
 * This checks native source invariants; interactive Ubuntu testing still
 * requires a Qt build and a session/keyring/backend.
 */
$root=dirname(__DIR__,2);
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$dialog=$read('Ubuntu/ConfigDialog.cpp');
$header=$read('Ubuntu/ConfigDialog.h');
$client=$read('Ubuntu/MonitorClient.cpp');
$checks=[
    'Load display-only User No from the canonical settings file'=>
        str_contains($dialog,'rememberedUserNo=cfg.value("User/no").toString().trimmed();') &&
        str_contains($header,'QString rememberedUserNo;'),
    'Remembered account accessor does not require an active session'=>
        str_contains($header,'QString getRememberedUserNo() const') &&
        str_contains($header,'return activeUserNo.isEmpty() ? rememberedUserNo : activeUserNo;'),
    'Authenticated accessor still requires an actual secure token'=>
        str_contains($header,'return authToken.isEmpty() ? QString() : activeUserNo;'),
    'Settings User No field is visible even if token restoration failed'=>
        str_contains($dialog,'const bool showUserNo=!displayUserNo.isEmpty();') &&
        str_contains($dialog,'accountFieldLabel->setVisible(showUserNo);') &&
        str_contains($dialog,'accountInfoLabel->setVisible(showUserNo);') &&
        str_contains($dialog,'(previously signed in)'),
    'Ordinary settings saves cannot overwrite remembered identity with blanks'=>
        str_contains($dialog,'if(!authToken.isEmpty() && !activeUserNo.isEmpty()){') &&
        str_contains($dialog,'cfg.setValue("User/no",activeUserNo);'),
    'Secret token stays in keyring, not in settings.ini'=>
        str_contains($dialog,'"secret-tool",{"lookup"') &&
        !str_contains($dialog,'cfg.setValue("Auth/token"'),
    'Temporary offline restore keeps saved session rather than signing out'=>
        str_contains($dialog,'if(httpStatus==401 || httpStatus==403)') &&
        str_contains($dialog,'Server unavailable · saved session retained') &&
        str_contains($dialog,'monitorId=rememberedMonitorId;'),
    'Only explicit backend authorization denial removes active token'=>
        str_contains($dialog,'Session expired · Sign in again') &&
        str_contains($dialog,'response.insert("_http_status"'),
    'Explicit signout and server change clear remembered identity'=>
        str_contains($dialog,'clearStoredToken(oldHost,oldPort);'.PHP_EOL.'    clearSessionIdentity();') &&
        str_contains($dialog,'rememberedUserNo.clear();') &&
        str_contains($dialog,'cfg.remove("User/no");'),
    'Backend restore parses string or numeric user number'=>
        str_contains($dialog,'account.value("user_no").toVariant().toString().trimmed()'),
    'Tray reads User No immediately before any network restore'=>
        strpos($client,'signedUserNo=savedIdentity.value("User/no").toString().trimmed();') <
        strpos($client,'setupSystemTray();'),
    'Tray immediately refreshes status after session restore'=>
        str_contains($client,'signedUserNo=saved.getRememberedUserNo();') &&
        str_contains($client,'updateStatusDisplay();'.PHP_EOL.'    });'.PHP_EOL.'    // Fetch configuration from API'),
    'Tray keeps the identity visible but labels an unauthenticated session'=>
        str_contains($client,'User No: %1 (sign-in required)') &&
        str_contains($client,'QStringLiteral("User No: %1").arg(signedUserNo)'),
    'Session change keeps tray identity in sync, including signout'=>
        str_contains($client,'signedUserNo = dialog->getRememberedUserNo();'),
];
$fail=0;
foreach($checks as $name=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$pass)++$fail;
}
echo count($checks).' Ubuntu remembered User No checks, '.$fail.' failed'.PHP_EOL;
exit($fail?1:0);
