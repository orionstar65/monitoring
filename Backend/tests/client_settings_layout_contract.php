<?php
// Run: php Backend/tests/client_settings_layout_contract.php
// Source-level contracts only: verify Windows/MSYS2, macOS and Qt in their
// native toolchains and interact with all four tabs before release.
$root=dirname(__DIR__,2);
$source=static function(string $path) use ($root):string {
    $text=file_get_contents($root.'/'.$path);
    if($text===false)throw new RuntimeException('Missing '.$path);
    return $text;
};
$windows=$source('Windows/MonitorClient.cpp');
$ubuntu=$source('Ubuntu/ConfigDialog.cpp');
$mac=$source('Mac/MonitorClient/AppDelegate.swift');
$checks=[
    'Windows tabs use compact client-area measurements'=>
        str_contains($windows,'{SETTINGS_TABS,8,44,464,244}') &&
        str_contains($windows,'AdjustWindowRectEx(&outer,style,FALSE,exStyle)') &&
        str_contains($windows,'const int clientWidth=480;'),
    'Windows short tabs no longer inherit the Monitor height'=>
        str_contains($windows,'settingsActiveTab<2?244:settingsActiveTab==2?175:197') &&
        str_contains($windows,'layoutSettingsTabs(dlg);'.PHP_EOL.'            updateUnifiedVisibility(dlg);'),
    'Windows account, monitor and updates fields share alignment'=>
        str_contains($windows,'{SETTINGS_IP,104,100,350,24}') &&
        str_contains($windows,'{SETTINGS_MONITOR,104,100,254,174}') &&
        str_contains($windows,'{SETTINGS_UPDATE_URL,104,104,350,24}'),
    'Ubuntu uses direct tabs without nested scrolling and with compact margins'=>
        !str_contains($ubuntu,'auto *scroll=new QScrollArea(this)') &&
        str_contains($ubuntu,'layout->setContentsMargins(8,7,8,6)') &&
        str_contains($ubuntu,'resize(540,380)'),
    'Ubuntu uses tight, aligned form rows'=>
        substr_count($ubuntu,'setVerticalSpacing(5)')>=3 &&
        substr_count($ubuntu,'setHorizontalSpacing(10)')>=3 &&
        str_contains($ubuntu,'serverForm->setLabelAlignment(Qt::AlignLeft|Qt::AlignVCenter)') &&
        str_contains($ubuntu,'monitorForm->setLabelAlignment(Qt::AlignLeft|Qt::AlignVCenter)'),
    'macOS tabs fit the compact window and preserve visibility logic'=>
        str_contains($mac,'width: width, height: 385') &&
        str_contains($mac,'tabs.heightAnchor.constraint(equalToConstant: 284)') &&
        str_contains($mac,'monitorPane.isHidden = !enrolled && pendingUserNo == nil'),
    'macOS monitor Save and Lock share a horizontal action row'=>
        str_contains($mac,'let monitorActions = NSStackView(views: [save, lockSettings])') &&
        substr_count($mac,'let lockSettings = action("Lock Settings", "lock")')===1 &&
        str_contains($mac,'saveRow.isHidden = false'),
    'All clients retain the four settings groups'=>
        str_contains($windows,'L"Account & Server"') &&
        str_contains($windows,'L"Monitor"') &&
        str_contains($windows,'L"Preferences"') &&
        str_contains($windows,'L"Updates"') &&
        str_contains($ubuntu,'createPage("Account & Server")') &&
        str_contains($ubuntu,'createPage("Monitor")') &&
        str_contains($ubuntu,'createPage("Preferences")') &&
        str_contains($ubuntu,'createPage("Updates")') &&
        str_contains($mac,'addSettingsTab("Account & Server"') &&
        str_contains($mac,'addSettingsTab("Monitor"') &&
        str_contains($mac,'addSettingsTab("Preferences"') &&
        str_contains($mac,'addSettingsTab("Updates"'),
    'Client account summaries show User No without full name'=>
        str_contains($windows,'enrolled?"User No: "+signedInUsername:""') &&
        str_contains($ubuntu,'accountInfoLabel->setText(QStringLiteral("User No: %1").arg(activeUserNo))') &&
        str_contains($mac,'signedInSummary.stringValue="User No: \\(no)"'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
echo count($checks).' cross-platform layout checks, '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
