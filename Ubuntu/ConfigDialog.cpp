#include "ConfigDialog.h"
#include "version.h"
#include <QCryptographicHash>
#include <QSaveFile>
#include <QFileInfo>
#include <QRegularExpression>
#include <QSysInfo>
#include <memory>
#include <QApplication>
#include <QCollator>
#include <algorithm>
#include <vector>
#include <QCheckBox>
#include <QDir>
#include <QEventLoop>
#include <QFile>
#include <QFont>
#include <QFormLayout>
#include <QFrame>
#include <QGroupBox>
#include <QHBoxLayout>
#include <QIcon>
#include <QJsonDocument>
#include <QMessageBox>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QProcess>
#include <QScrollArea>
#include <QSettings>
#include <QSignalBlocker>
#include <QStandardPaths>
#include <QTimer>
#include <QUrl>
#include <QVBoxLayout>
#include <QHBoxLayout>
#include <QRadioButton>
#include <QButtonGroup>
#include <QStackedWidget>

ConfigDialog::ConfigDialog(QWidget *parent) : QDialog(parent) {
    setWindowTitle("MonitorClient v1.5 — Settings");
    setMinimumSize(500,420);
    resize(560,640);
    setModal(true);
    auto *outer=new QVBoxLayout(this);
    outer->setContentsMargins(14,10,14,12);
    outer->setSpacing(10);
    auto *scroll=new QScrollArea(this);
    scroll->setWidgetResizable(true);
    scroll->setFrameShape(QFrame::NoFrame);
    scroll->setAlignment(Qt::AlignTop);
    auto *page=new QWidget(scroll);
    auto *content=new QVBoxLayout(page);
    content->setContentsMargins(2,0,2,2);
    content->setSpacing(12);
    content->setAlignment(Qt::AlignTop);
    scroll->setWidget(page);
    outer->addWidget(scroll);
    auto *header=new QHBoxLayout();
    auto *icon=new QLabel(page);
    QIcon appIcon=QApplication::windowIcon();
    if(appIcon.isNull())appIcon=QIcon::fromTheme("accessories-text-editor");
    icon->setPixmap(appIcon.pixmap(42,42));
    header->addWidget(icon);
    auto *brand=new QVBoxLayout();
    auto *name=new QLabel("MonitorClient");
    QFont font=name->font();font.setBold(true);font.setPointSize(15);name->setFont(font);
    brand->addWidget(name);brand->addWidget(new QLabel("v1.5"));
    header->addLayout(brand);header->addStretch();
    content->addLayout(header);

    auto *serverGroup=new QGroupBox("Server Settings",page);
    auto *serverForm=new QFormLayout(serverGroup);
    serverIPEdit=new QLineEdit(serverGroup);
    serverPortEdit=new QSpinBox(serverGroup);
    serverPortEdit->setRange(1,65535);
    serverForm->addRow("Server IP",serverIPEdit);
    serverForm->addRow("Port",serverPortEdit);
    userNoInput=new QLineEdit(serverGroup);
    userNoInput->setPlaceholderText("User No");
    passwordInput=new QLineEdit(serverGroup);
    passwordInput->setEchoMode(QLineEdit::Password);
    passwordInput->setPlaceholderText("Password");
    userNoFieldLabel=new QLabel("User No",serverGroup);
    passwordFieldLabel=new QLabel("Password",serverGroup);
    serverForm->addRow(userNoFieldLabel,userNoInput);
    serverForm->addRow(passwordFieldLabel,passwordInput);
    accountFieldLabel=new QLabel("Signed-in user",serverGroup);
    accountInfoLabel=new QLabel("—",serverGroup);
    accountInfoLabel->setWordWrap(true);
    accountInfoLabel->setTextInteractionFlags(Qt::TextSelectableByMouse);
    serverForm->addRow(accountFieldLabel,accountInfoLabel);
    testButton=new QPushButton("Sign In",serverGroup);
    connectionStatusLabel=new QLabel("● Not tested",serverGroup);
    serverForm->addRow(testButton,connectionStatusLabel);
    content->addWidget(serverGroup);
    auto *userGroup=new QWidget(page);
    auto *userForm=new QFormLayout(userGroup);
    userSelector=new QComboBox(userGroup);
    userSelector->setMinimumWidth(350);
    userSelector->setSizeAdjustPolicy(QComboBox::AdjustToMinimumContentsLengthWithIcon);
    userSelector->setMinimumContentsLength(28);
    userForm->addRow("User:",userSelector);
    logoutButton=new QPushButton("Sign Out",serverGroup);
    serverForm->addRow("",logoutButton);
    assignmentStatusLabel=new QLabel(userGroup);
    assignmentStatusLabel->setWordWrap(true);
    assignmentStatusLabel->setStyleSheet("color:#bf3838;");
    userForm->addRow("",assignmentStatusLabel);
    userGroup->setVisible(false); // Password sign-in replaces user directory selection.
    content->addWidget(userGroup);
    monitorGroup=new QGroupBox("Monitor Settings",page);
    auto *monitorForm=new QFormLayout(monitorGroup);
    monitorSelector=new QComboBox(monitorGroup);
    osRadios=new QButtonGroup(monitorGroup);
    auto *osRow=new QWidget(monitorGroup);
    auto *osLayout=new QHBoxLayout(osRow);osLayout->setContentsMargins(0,0,0,0);
    for(const auto &value:QStringList{"Windows","Mac","Ubuntu"}){
        auto *radio=new QRadioButton(value,osRow);
        osRadios->addButton(radio);
        osLayout->addWidget(radio);
        if(value=="Ubuntu")radio->setChecked(true);
    }
    machineRadios=new QButtonGroup(monitorGroup);
    auto *machineRow=new QWidget(monitorGroup);
    auto *machineLayout=new QHBoxLayout(machineRow);machineLayout->setContentsMargins(0,0,0,0);
    for(const auto &value:QStringList{"Laptop","Desktop"}){
        auto *radio=new QRadioButton(value,machineRow);
        machineRadios->addButton(radio);
        machineLayout->addWidget(radio);
        if(value=="Desktop")radio->setChecked(true);
    }
    monitorIPLabel=new QLabel("—",monitorGroup);
    remarkEdit=new QLineEdit(monitorGroup);
    osInfo=new QLabel("—",monitorGroup);
    machineInfo=new QLabel("—",monitorGroup);
    remarkInfo=new QLabel("—",monitorGroup);
    remarkInfo->setWordWrap(true);
    remarkInfo->setTextInteractionFlags(Qt::TextSelectableByMouse);
    osStack=new QStackedWidget(monitorGroup);
    osStack->addWidget(osInfo);osStack->addWidget(osRow);
    machineStack=new QStackedWidget(monitorGroup);
    machineStack->addWidget(machineInfo);machineStack->addWidget(machineRow);
    remarkStack=new QStackedWidget(monitorGroup);
    remarkStack->addWidget(remarkInfo);remarkStack->addWidget(remarkEdit);
    editButton=new QPushButton("Edit",monitorGroup);
    auto *selectionRow=new QWidget(monitorGroup);
    auto *selectionLayout=new QHBoxLayout(selectionRow);
    selectionLayout->setContentsMargins(0,0,0,0);
    selectionLayout->addWidget(monitorSelector,1);
    selectionLayout->addWidget(editButton);
    monitorForm->addRow("Monitor",selectionRow);
    monitorForm->addRow("OS",osStack);
    monitorForm->addRow("Machine",machineStack);
    monitorForm->addRow("IP address",monitorIPLabel);
    monitorForm->addRow("Remark",remarkStack);
    content->addWidget(monitorGroup);
    notificationsCheck=new QCheckBox("Show status notifications",page);
    content->addWidget(notificationsCheck);
    auto *updatesGroup=new QGroupBox("Updates",page);
    auto *updateForm=new QFormLayout(updatesGroup);
    updateServerInput=new QLineEdit(updatesGroup);
    updateServerInput->setPlaceholderText("http://192.168.2.241:8765");
    updateServerInput->setToolTip("URL serving manifest.json and versioned packages via python -m http.server");
    updateForm->addRow("Update server",updateServerInput);
    auto *updateActions=new QHBoxLayout();
    checkUpdateButton=new QPushButton("Check new version",updatesGroup);
    installUpdateButton=new QPushButton("Update",updatesGroup);
    installUpdateButton->setEnabled(false);
    updateActions->addWidget(checkUpdateButton);
    updateActions->addWidget(installUpdateButton);
    updateActions->addStretch();
    updateForm->addRow("",updateActions);
    updateStatus=new QLabel("Installed: "+QString(MONITORAPP_VERSION),updatesGroup);
    updateStatus->setWordWrap(true);
    updateForm->addRow("",updateStatus);
    content->addWidget(updatesGroup);
    updateNetwork=new QNetworkAccessManager(this);
    connect(checkUpdateButton,&QPushButton::clicked,this,&ConfigDialog::onCheckUpdate);
    connect(installUpdateButton,&QPushButton::clicked,this,&ConfigDialog::onInstallUpdate);
    connect(updateServerInput,&QLineEdit::textChanged,this,[this](){
        availableUpdateUrl.clear();
        installUpdateButton->setEnabled(false);
    });
    auto *toolsRow=new QHBoxLayout();
    sendAllButton=new QPushButton("Send All Logs",page);
    refreshConfigButton=new QPushButton("Refresh Configuration",page);
    toolsRow->addWidget(sendAllButton);
    toolsRow->addWidget(refreshConfigButton);
    content->addLayout(toolsRow);
    connect(sendAllButton,&QPushButton::clicked,this,[this](){emit sendAllRequested();});
    connect(refreshConfigButton,&QPushButton::clicked,this,[this](){emit refreshConfigurationRequested();});
    auto *buttons=new QHBoxLayout();
    saveButton=new QPushButton("Save",this);
    lockSettingsButton=new QPushButton("Lock Settings",this);
    auto *closeButton=new QPushButton("Close",this);
    buttons->addStretch();
    buttons->addWidget(saveButton);
    buttons->addWidget(lockSettingsButton);
    buttons->addWidget(closeButton);
    outer->addLayout(buttons);
    connect(testButton,&QPushButton::clicked,this,&ConfigDialog::onSignInClicked);
    connect(logoutButton,&QPushButton::clicked,this,&ConfigDialog::onLogoutClicked);
    connect(userSelector,QOverload<int>::of(&QComboBox::currentIndexChanged),this,&ConfigDialog::onUserChanged);
    connect(monitorSelector,QOverload<int>::of(&QComboBox::currentIndexChanged),this,&ConfigDialog::onMonitorChanged);
    connect(saveButton,&QPushButton::clicked,this,&ConfigDialog::onSaveClicked);
    connect(editButton,&QPushButton::clicked,this,&ConfigDialog::onEditClicked);
    connect(lockSettingsButton,&QPushButton::clicked,this,&ConfigDialog::onLockSettingsClicked);
    connect(closeButton,&QPushButton::clicked,this,&ConfigDialog::reject);
    loadSettings();
    // Apply immediately: a disabled toggle must suppress system tray
    // notifications without waiting for a separate Save click.
    connect(notificationsCheck,&QCheckBox::toggled,this,[this](bool enabled){
        QDir().mkpath(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient");
        QSettings settings(settingsPath(),QSettings::IniFormat);
        settings.setValue("Notifications/enabled",enabled);
        settings.sync();
    });
    loadRememberedSession();
    refreshMonitors();
    updateUI();
}
QString ConfigDialog::settingsPath() const {
    return QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient/settings.ini";
}
QString ConfigDialog::getServerIP() const {return serverIPEdit->text().trimmed();}
int ConfigDialog::getServerPort() const {return serverPortEdit->value();}
QString ConfigDialog::getClientName() const {return "Device #"+QString::number(monitorId);}
void ConfigDialog::loadSettings(){
    QString path=settingsPath();
    if(!QFile::exists(path))path="settings.ini";
    QSettings cfg(path,QSettings::IniFormat);
    serverIPEdit->setText(cfg.value("Server/ip","192.168.1.45").toString());
    serverPortEdit->setValue(cfg.value("Server/port",8924).toInt());
    notificationsCheck->setChecked(cfg.value("Notifications/enabled",true).toBool());
    updateServerInput->setText(cfg.value("Updates/url","").toString());
}
void ConfigDialog::saveSettings(){
    QDir().mkpath(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient");
    for(const QString &path:{settingsPath(),QStringLiteral("settings.ini")}){
        QSettings cfg(path,QSettings::IniFormat);
        cfg.setValue("Server/ip",getServerIP());
        cfg.setValue("Server/port",getServerPort());
        cfg.setValue("Notifications/enabled",notificationsCheck->isChecked());
        cfg.setValue("Monitor/id",monitorId);
        cfg.setValue("User/no",activeUserNo);
        cfg.setValue("Updates/url",updateServerInput->text().trimmed());
    }
}
QJsonObject ConfigDialog::authRequest(const QJsonObject &payload,bool authenticated){
    QNetworkAccessManager manager;
    const QUrl url(QString("%1://%2:%3/client_auth.php")
       .arg(getServerPort()==8924?"http":"https").arg(getServerIP()).arg(getServerPort()));
    QNetworkRequest request(url);
    request.setHeader(QNetworkRequest::ContentTypeHeader,"application/json");
    if(authenticated&&!authToken.isEmpty())
        request.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply=manager.post(request,QJsonDocument(payload).toJson());
    QEventLoop loop;QTimer timer;timer.setSingleShot(true);
    connect(&timer,&QTimer::timeout,reply,&QNetworkReply::abort);
    connect(reply,&QNetworkReply::finished,&loop,&QEventLoop::quit);
    timer.start(8000);
    if(!reply->isFinished())loop.exec();
    QJsonObject response=QJsonDocument::fromJson(reply->readAll()).object();
    if(reply->error()!=QNetworkReply::NoError&&!response.contains("error"))
        response.insert("error",timer.isActive()?reply->errorString():"Server request timed out");
    reply->deleteLater();return response;
}
bool ConfigDialog::testConnection(){
    QNetworkAccessManager manager;
    const QUrl url(QString("%1://%2:%3/html/auth/login")
       .arg(getServerPort()==8924?"http":"https").arg(getServerIP()).arg(getServerPort()));
    QNetworkRequest request(url);
    QNetworkReply *reply=manager.get(request);
    QEventLoop loop;QTimer timer;timer.setSingleShot(true);
    connect(&timer,&QTimer::timeout,reply,&QNetworkReply::abort);
    connect(reply,&QNetworkReply::finished,&loop,&QEventLoop::quit);
    timer.start(6000);if(!reply->isFinished())loop.exec();
    const int status=reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
    const bool ok=reply->error()==QNetworkReply::NoError&&status>=200&&status<400;
    reply->deleteLater();return ok;
}
void ConfigDialog::onSignInClicked(){
    const QString number=userNoInput->text().trimmed();
    const QString password=passwordInput->text();
    if(number.isEmpty()||password.isEmpty()){
        connectionStatusLabel->setText("Enter User No and password");
        return;
    }
    testButton->setEnabled(false);
    connectionStatusLabel->setText("Signing in...");
    QApplication::processEvents();
    const auto result=authRequest({{"action","login"},{"user_no",number},{"password",password}});
    passwordInput->clear();
    testButton->setEnabled(true);
    if(!result.value("success").toBool()){
        connectionStatusLabel->setText(result.value("error").toString("Sign in failed"));
        return;
    }
    const QString token=result.value("token").toString();
    if(token.size()!=64){
        connectionStatusLabel->setText("Invalid sign-in token");
        return;
    }
    authToken=token;monitorId=result.value("monitor_id").toInt();
    activeUserNo=number;
    activeUserName=result.value("user").toObject().value("name").toString();
    assignmentLocked=false;settingsLocked=false;
    rememberToken(token);
    // Sign In commits the authenticated server connection, but must not
    // silently apply draft notification or monitor settings. Those need Save.
    QDir().mkpath(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient");
    for(const QString &path:{settingsPath(),QStringLiteral("settings.ini")}){
        QSettings cfg(path,QSettings::IniFormat);
        cfg.setValue("Server/ip",getServerIP());
        cfg.setValue("Server/port",getServerPort());
    }
    connectionStatusLabel->setText("Signed in");
    updateMonitorList(result.value("monitors").toArray());
    updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onTestConnectionClicked(){
    testButton->setEnabled(false);
    connectionStatusLabel->setText("● Testing...");
    const bool ok=testConnection();
    testButton->setEnabled(true);
    connectionStatusLabel->setText(ok?"● Server reachable":"● Server unavailable (check IP, port and TLS)");
    connectionStatusLabel->setStyleSheet(ok?"color:#16803c":"color:#d32f2f");
    if(ok && saveServerIfChanged()){
        refreshDirectory();
        refreshMonitors();
        updateUI();
    }
}
void ConfigDialog::clearStoredToken(const QString &host,int port){
    QProcess secret;
    secret.start("secret-tool",{"clear","service","acc-monitor-v15","host",host+":"+QString::number(port)});
    secret.waitForFinished(2500);
}
QString ConfigDialog::readRememberedToken() const {
    QProcess secret;
    secret.start("secret-tool",{"lookup","service","acc-monitor-v15","host",getServerIP()+":"+QString::number(getServerPort())});
    if(!secret.waitForFinished(2500)||secret.exitCode()!=0)return {};
    return QString::fromUtf8(secret.readAllStandardOutput()).trimmed();
}
void ConfigDialog::rememberToken(const QString &token){
    QProcess secret;
    secret.start("secret-tool",{"store","--label=MonitorClient v1.5 device",
        "service","acc-monitor-v15","host",getServerIP()+":"+QString::number(getServerPort())});
    if(!secret.waitForStarted(1500)){
        QMessageBox::warning(this,"Session storage",
            "The secure keyring is unavailable. Install secret-tool/libsecret and unlock a Secret Service keyring to stay signed in after restarting the app.");
        return;
    }
    secret.write(token.toUtf8());secret.write("\n");secret.closeWriteChannel();
    if(!secret.waitForFinished(3500)||secret.exitCode()!=0)
        QMessageBox::warning(this,"Keyring","Install and unlock a Secret Service keyring to remember your selected user.");
}
void ConfigDialog::loadRememberedSession(){
    authToken=readRememberedToken();
    if(authToken.isEmpty())return;
    auto state=authRequest({{"action","restore"}},true);
    if(!state.value("success").toBool()){
        authToken.clear();
        connectionStatusLabel->setText("Session unavailable · Sign in again");
        return;
    }
    monitorId=state.value("monitor_id").toInt();
    const QJsonObject account=state.value("user").toObject();
    activeUserNo=account.value("user_no").toString();
    activeUserName=account.value("name").toString();
    assignmentLocked=state.value("assignment_locked").toBool();
    connectionStatusLabel->setText("Signed in as "+activeUserNo);
    // An authenticated session may exist before a monitor is selected.
}
bool ConfigDialog::saveServerIfChanged(){
    if(getServerIP().isEmpty()){QMessageBox::warning(this,"Server","Enter a valid server IP.");return false;}
    QSettings previous(settingsPath(),QSettings::IniFormat);
    const QString oldHost=previous.value("Server/ip","192.168.1.45").toString();
    const int oldPort=previous.value("Server/port",8924).toInt();
    if(oldHost!=getServerIP()||oldPort!=getServerPort()){
        if(!authToken.isEmpty()){
            const QString draftHost=getServerIP();
            const int draftPort=getServerPort();
            serverIPEdit->setText(oldHost);
            serverPortEdit->setValue(oldPort);
            authRequest({{"action","logout"}},true);
            serverIPEdit->setText(draftHost);
            serverPortEdit->setValue(draftPort);
        }
        clearStoredToken(oldHost,oldPort);
        authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();activeUserName.clear();
        emit sessionStateChanged();
    }
    saveSettings();
    return true;
}
void ConfigDialog::refreshDirectory(){
    auto result=authRequest({{"action","directory"}});
    const QSignalBlocker blocked(userSelector);
    const QString current=!pendingUserNo.isEmpty()?pendingUserNo:(authToken.isEmpty()?QString():activeUserNo);
    userSelector->clear();
    userSelector->addItem("Select a user…",QString());
    if(!result.value("success").toBool()){
        directoryError=result.value("error").toString(
            "Cannot load users. Verify the server address, TLS and enrollment configuration.");
        userSelector->addItem("User list unavailable — check server",QString());
        userSelector->setCurrentIndex(0);
        updateUI();
        return;
    }
    const auto records=result.value("users").toArray();
    directoryError=records.isEmpty()
        ? "No users available." : QString();
    for(auto entry:records){
        const auto user=entry.toObject();
        const QString number=user.value("user_no").toString();
        if(number.isEmpty())continue;
        const int n=user.value("device_count").toInt();
        const QString label=QString("%1: %2 (%3 Devices)")
            .arg(number,user.value("full_name").toString()).arg(n);
        userSelector->addItem(label,number);
    }
    const int ix=userSelector->findData(current);
    userSelector->setCurrentIndex(ix>=0?ix:0);
}
void ConfigDialog::refreshMonitors(){
    if(authToken.isEmpty()){updateMonitorList({});return;}
    const auto result=authRequest({{"action","restore"}},true);
    if(!result.value("success").toBool()){
        authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();activeUserName.clear();
        updateMonitorList({});updateUI();return;
    }
    monitorId=result.value("monitor_id").toInt();
    assignmentLocked=result.value("assignment_locked").toBool();
    settingsLocked=false;
    const QJsonObject account=result.value("user").toObject();
    activeUserNo=account.value("user_no").toString();
    activeUserName=account.value("name").toString();
    if(userNoInput)userNoInput->setText(activeUserNo);
    updateMonitorList(result.value("monitors").toArray());
}
void ConfigDialog::updateMonitorList(const QJsonArray &items){
    // Sort once and use the same order for displayed options and draft details.
    std::vector<QJsonObject> ordered;
    ordered.reserve(static_cast<size_t>(items.size()));
    for(const auto &item:items)ordered.push_back(item.toObject());
    QCollator collator;
    collator.setNumericMode(true);
    collator.setCaseSensitivity(Qt::CaseInsensitive);
    std::stable_sort(ordered.begin(),ordered.end(),[&](const QJsonObject &a,const QJsonObject &b){
        for(const char *field:{"type","platform","ip_address"}){
            const int result=collator.compare(a.value(field).toString(),b.value(field).toString());
            if(result!=0)return result<0;
        }
        return a.value("id").toInt()<b.value("id").toInt();
    });
    assignedMonitors=QJsonArray();
    for(const auto &m:ordered)assignedMonitors.append(m);
    const QSignalBlocker blocked(monitorSelector);
    monitorSelector->clear();
    for(const auto &m:ordered){
        monitorSelector->addItem(QString("%1 · %2-%3 #%4").arg(
            m.value("ip_address").toString("—"),m.value("platform").toString("—"),
            m.value("type").toString("Desktop")).arg(m.value("id").toInt()),
            m.value("id").toInt());
    }
    monitorSelector->addItem("Add new...",-1);
    const int ix=monitorSelector->findData(monitorId);
    const int newMonitorIndex=monitorSelector->findData(-1);
    monitorSelector->setCurrentIndex(!pendingUserNo.isEmpty()?newMonitorIndex:(ix>=0?ix:newMonitorIndex));
    onMonitorChanged(monitorSelector->currentIndex());
}
void ConfigDialog::onMonitorChanged(int){
    const int id=monitorSelector->currentData().toInt();
    if((settingsLocked||assignmentLocked)&&id!=monitorId&&!authToken.isEmpty()){
        const QSignalBlocker blocked(monitorSelector);
        monitorSelector->setCurrentIndex(monitorSelector->findData(monitorId));
        return;
    }
    if(id==-1){
        monitorEditing=true;
        remarkEdit->clear();
        monitorIPLabel->setText("—");
        settingsLocked=false;
        updateUI();return;
    }
    monitorEditing=false;
    if(id>0 && !pendingUserNo.isEmpty()){
        // These are directory previews, not authorized device sessions.
        // Do not try to activate another machine's monitor.
        monitorEditing=false;
    }
    // Choosing a monitor only previews its details. Save commits selection.
    for(const auto &value:assignedMonitors){
        const QJsonObject m=value.toObject();
        if(m.value("id").toInt()!=id)continue;
        const QString remark=m.value("remark").toString();
        remarkEdit->setText(remark);
        remarkInfo->setText(remark.isEmpty()?"—":remark);
        const QString platform=m.value("platform").toString("Ubuntu");
        osInfo->setText(platform);
        for(auto *button:osRadios->buttons())
            if(button->text()==platform)button->setChecked(true);
        const QString type=m.value("type").toString().toLower()=="desktop"?"Desktop":"Laptop";
        machineInfo->setText(type);
        for(auto *button:machineRadios->buttons())
            if(button->text()==type)button->setChecked(true);
        settingsLocked=(id==monitorId)&&m.value("settings_locked").toBool();
        monitorIPLabel->setText(m.value("ip_address").toString("—"));
        break;
    }
    updateUI();
}
void ConfigDialog::onUserChanged(int){
    const QString number=userSelector->currentData().toString();
    if(!pendingUserNo.isEmpty()&&(number.isEmpty()||number==activeUserNo)){
        pendingUserNo.clear();
        refreshMonitors();
        updateUI();
        return;
    }
    if(number.isEmpty()||number==activeUserNo)return;
    if((assignmentLocked||settingsLocked)&&!authToken.isEmpty()){
        QMessageBox::warning(this,"Assignment locked",
            "User assignment is locked. Ask your administrator to edit this monitor.");
        const QSignalBlocker blocked(userSelector);
        const int ix=userSelector->findData(activeUserNo);
        userSelector->setCurrentIndex(ix>=0?ix:0);
        return;
    }
    if(authToken.isEmpty())onPairClicked();
    else onSwitchClicked();
}
void ConfigDialog::updateUI(){
    const bool enrolled=!authToken.isEmpty();
    const bool pending=!pendingUserNo.isEmpty();
    const bool locked=enrolled&&(settingsLocked||assignmentLocked);
    monitorGroup->setVisible(enrolled||pending);
    testButton->setVisible(!enrolled);
    userNoInput->setVisible(!enrolled);
    passwordInput->setVisible(!enrolled);
    userNoFieldLabel->setVisible(!enrolled);
    passwordFieldLabel->setVisible(!enrolled);
    accountFieldLabel->setVisible(enrolled);
    accountInfoLabel->setVisible(enrolled);
    if(enrolled)accountInfoLabel->setText(QStringLiteral("User No: %1\nFull name: %2")
        .arg(activeUserNo,activeUserName.isEmpty()?QStringLiteral("—"):activeUserName));
    logoutButton->setVisible(enrolled);
    logoutButton->setEnabled(enrolled);
    sendAllButton->setEnabled(enrolled&&monitorId>0);
    refreshConfigButton->setEnabled(enrolled&&monitorId>0);
    userSelector->setEnabled(!locked);
    monitorSelector->setEnabled(!locked);
    serverIPEdit->setEnabled(!locked);
    serverPortEdit->setEnabled(!locked);
    testButton->setEnabled(!locked);
    const bool hasLock=locked;
    assignmentStatusLabel->setVisible(hasLock||!directoryError.isEmpty());
    if(hasLock)
        assignmentStatusLabel->setText("User selection is locked. Ask your administrator to edit.");
    else if(!directoryError.isEmpty())
        assignmentStatusLabel->setText(directoryError);
    const int selectedId=monitorSelector->currentData().toInt();
    const bool adding=(enrolled||pending)&&selectedId==-1&&!locked;
    const bool existing=enrolled&&selectedId>0&&!pending;
    const bool editing=!locked&&(adding||(existing&&monitorEditing));
    osStack->setCurrentIndex(editing?1:0);
    machineStack->setCurrentIndex(editing?1:0);
    remarkStack->setCurrentIndex(editing?1:0);
    editButton->setVisible(existing&&!locked);
    editButton->setText(editing?"Cancel":"Edit");
    saveButton->setText(adding?"Add Monitor":"Save");
    saveButton->setVisible(true);
    saveButton->setEnabled(true);
    lockSettingsButton->setVisible(existing);
    lockSettingsButton->setEnabled(existing&&!locked);
    lockSettingsButton->setText(locked?"Settings Locked":"Lock Settings");
}
void ConfigDialog::onPairClicked(){
    if(!saveServerIfChanged())return;
    const QString number=userSelector->currentData().toString();
    if(number.isEmpty()){QMessageBox::warning(this,"Select User No","Choose a User No.");return;}
    const auto result=authRequest({{"action","enroll"},{"user_no",number}});
    if(!result.value("success").toBool()){
        if(result.value("code").toString()=="no_monitors"){
            pendingUserNo=number;
            settingsLocked=false;
            updateMonitorList(result.value("monitors").toArray());
            directoryError.clear();
            updateUI();return;
        }
        QMessageBox::warning(this,"User selection failed",result.value("error").toString("Could not load assigned monitors."));
        {
            const QSignalBlocker blocked(userSelector);
            userSelector->setCurrentIndex(0);
        }
        saveSettings();
        return;
    }
    authToken=result.value("token").toString();
    pendingUserNo.clear();
    monitorId=result.value("monitor_id").toInt();
    assignmentLocked=result.value("assignment_locked").toBool();
    activeUserNo=number;
    if(authToken.length()!=64||monitorId<1){
        authToken.clear();monitorId=0;
        QMessageBox::warning(this,"User selection failed","Invalid session details returned.");
        {
            const QSignalBlocker blocked(userSelector);
            userSelector->setCurrentIndex(0);
        }
        saveSettings();
        return;
    }
    rememberToken(authToken);
    refreshMonitors();saveSettings();updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onSwitchClicked(){
    if(!saveServerIfChanged())return;
    if(assignmentLocked||settingsLocked){QMessageBox::warning(this,"Assignment locked","Ask your administrator to edit this monitor.");return;}
    const auto result=authRequest({{"action","switch_user"},{"user_no",userSelector->currentData().toString()}},true);
    if(!result.value("success").toBool()){
        if(result.value("code").toString()=="no_monitors"){
            pendingUserNo=userSelector->currentData().toString();
            settingsLocked=false;
            updateMonitorList(result.value("monitors").toArray());
            directoryError.clear();
            updateUI();return;
        }
        QMessageBox::warning(this,"Cannot change user",result.value("error").toString("Ask your administrator to change the assignment."));
        refreshMonitors();saveSettings();updateUI();return;
    }
    authToken=result.value("token").toString();
    pendingUserNo.clear();
    activeUserNo=userSelector->currentData().toString();
    rememberToken(authToken);
    refreshMonitors();saveSettings();updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onLogoutClicked(){
    QSettings previous(settingsPath(),QSettings::IniFormat);
    const QString oldHost=previous.value("Server/ip","192.168.1.45").toString();
    const int oldPort=previous.value("Server/port",8924).toInt();
    if(!authToken.isEmpty()){
        // Revoke against the server that issued the saved token, even if
        // different server details were typed but never saved.
        const QString draftHost=getServerIP();
        const int draftPort=getServerPort();
        serverIPEdit->setText(oldHost);
        serverPortEdit->setValue(oldPort);
        authRequest({{"action","logout"}},true);
        serverIPEdit->setText(draftHost);
        serverPortEdit->setValue(draftPort);
    }
    clearStoredToken(oldHost,oldPort);
    authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();activeUserName.clear();pendingUserNo.clear();logoutPerformed=true;
    connectionStatusLabel->setText("Signed out");
    updateMonitorList({});updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onEditClicked(){
    const int id=monitorSelector->currentData().toInt();
    if(id<1||settingsLocked||assignmentLocked)return;
    if(monitorEditing){
        onMonitorChanged(monitorSelector->currentIndex()); // discard unsaved edits
    }else{
        monitorEditing=true;
        updateUI();
    }
}

void ConfigDialog::onLockSettingsClicked(){
    if(authToken.isEmpty()||settingsLocked||assignmentLocked)return;
    if(QMessageBox::question(this,"Lock monitor settings",
        "Lock monitor settings now? Only an administrator can unlock this monitor in the web portal.")
       !=QMessageBox::Yes)return;
    const auto reply=authRequest({
        {"action","lock_settings"},{"remark",remarkEdit->text().trimmed()},
        {"os",osRadios->checkedButton()?osRadios->checkedButton()->text():"Ubuntu"},
        {"machine",machineRadios->checkedButton()?machineRadios->checkedButton()->text().toLower():"desktop"}
    },true);
    if(!reply.value("success").toBool()){
        QMessageBox::warning(this,"Lock settings",reply.value("error").toString("Unable to lock monitor."));return;
    }
    settingsLocked=true;
    refreshMonitors();updateUI();
}

void ConfigDialog::onSaveClicked(){
    if(!saveServerIfChanged())return;
    if(!pendingUserNo.isEmpty()){
        if(monitorSelector->currentData().toInt()!=-1){
            QMessageBox::warning(this,"Monitor selection",
                "To reuse an existing monitor, restore this computer's saved device session. Otherwise choose Add new or ask an administrator.");
            return;
        }
        QJsonObject request={{"action","bootstrap_monitor"},{"user_no",pendingUserNo},
            {"remark",remarkEdit->text().trimmed()},
            {"os",osRadios->checkedButton()?osRadios->checkedButton()->text():"Ubuntu"},
            {"machine",machineRadios->checkedButton()?machineRadios->checkedButton()->text().toLower():"desktop"}};
        const auto result=authRequest(request,false);
        if(!result.value("success").toBool()){
            QMessageBox::warning(this,"Add monitor",result.value("error").toString("Could not create first monitor."));return;
        }
        const QString token=result.value("token").toString();
        const int created=result.value("monitor_id").toInt();
        if(token.length()!=64||created<1){
            QMessageBox::warning(this,"Add monitor","Invalid enrollment response.");return;
        }
        authToken=token;monitorId=created;activeUserNo=pendingUserNo;pendingUserNo.clear();
        directoryError.clear();
        assignmentLocked=false;settingsLocked=false;
        rememberToken(authToken);refreshMonitors();saveSettings();updateUI();
        emit sessionStateChanged();return;
    }
    if(authToken.isEmpty()){connectionStatusLabel->setText("● Sign in to select a monitor");return;}
    const int id=monitorSelector->currentData().toInt();
    if(id>0&&!monitorEditing){
        if(id!=monitorId){
            const auto result=authRequest({{"action","select"},{"monitor_id",id}},true);
            if(!result.value("success").toBool()){
                QMessageBox::warning(this,"Monitor selection",result.value("error").toString("Monitor selection denied."));
                refreshMonitors();return;
            }
            monitorId=id;
        }
        saveSettings();
        refreshMonitors();updateUI();
        emit sessionStateChanged();
        return;
    }
    if((settingsLocked||assignmentLocked)&&id!=-1){
        QMessageBox::warning(this,"Settings locked","Ask your administrator to unlock this monitor.");return;
    }
    if(id<1 && id!=-1){
        QMessageBox::warning(this,"Monitor","Select a monitor.");return;
    }
    const QString os=osRadios->checkedButton()?osRadios->checkedButton()->text():"Ubuntu";
    const QString machine=machineRadios->checkedButton()?machineRadios->checkedButton()->text().toLower():"desktop";
    QJsonObject request={{"action",id==-1?"create_monitor":"save_monitor"},
        {"remark",remarkEdit->text().trimmed()},
        {"os",os},{"machine",machine}};
    if(id>0)request.insert("monitor_id",id);
    if(id>0&&id!=monitorId){
        QMessageBox::warning(this,"Monitor","Select the intended monitor first.");return;
    }
    const auto result=authRequest(request,true);
    if(!result.value("success").toBool()){
        QMessageBox::warning(this,"Monitor",result.value("error").toString("Unable to save monitor."));return;
    }
    if(id==-1){
        const int created=result.value("monitor_id").toInt();
        if(created<1){QMessageBox::warning(this,"Monitor","Invalid new monitor ID.");return;}
        const auto selection=authRequest({{"action","select"},{"monitor_id",created}},true);
        if(!selection.value("success").toBool()){
            QMessageBox::warning(this,"Monitor",selection.value("error").toString("Monitor created, but selecting it failed."));
            refreshMonitors();return;
        }
        monitorId=created;
    }
    saveSettings();refreshMonitors();updateUI();
    emit sessionStateChanged();
}


namespace {
QUrl monitorUpdateBase(const QString &text) {
    QUrl url(text.trimmed());
    if(!url.isValid() || (url.scheme()!="http" && url.scheme()!="https") ||
       url.host().isEmpty() || !url.userInfo().isEmpty() ||
       url.hasQuery() || url.hasFragment())return {};
    QString p=url.path();
    if(!p.endsWith('/'))p+='/';
    url.setPath(p);
    return url;
}
bool monitorNewerVersion(const QString &remote,const QString &installed) {
    const QRegularExpression re(R"(^[0-9]+(\.[0-9]+){1,2}$)");
    if(!re.match(remote).hasMatch() || !re.match(installed).hasMatch())return false;
    auto a=remote.split('.');auto b=installed.split('.');
    for(int i=0;i<3;i++){
        const int x=i<a.size()?a[i].toInt():0;
        const int y=i<b.size()?b[i].toInt():0;
        if(x!=y)return x>y;
    }
    return false;
}
}
void ConfigDialog::onCheckUpdate(){
    availableUpdateUrl.clear();
    installUpdateButton->setEnabled(false);
    const QUrl base=monitorUpdateBase(updateServerInput->text());
    if(!base.isValid()){
        updateStatus->setText("Enter an http(s) update server URL.");
        return;
    }
    QDir().mkpath(QFileInfo(settingsPath()).absolutePath());
    QSettings cfg(settingsPath(),QSettings::IniFormat);
    cfg.setValue("Updates/url",base.toString());
    cfg.sync();
    checkUpdateButton->setEnabled(false);
    updateStatus->setText("Checking for an Ubuntu update...");
    QNetworkRequest req(base.resolved(QUrl("manifest.json")));
    req.setAttribute(QNetworkRequest::FollowRedirectsAttribute,false);
    auto *reply=updateNetwork->get(req);
    connect(reply,&QNetworkReply::finished,this,[this,reply,base](){
        checkUpdateButton->setEnabled(true);
        const int code=reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        const QByteArray data=reply->readAll();
        if(reply->error()!=QNetworkReply::NoError || code!=200 || data.size()>256*1024){
            updateStatus->setText("Update check failed: "+reply->errorString());
            reply->deleteLater();return;
        }
        const QJsonObject root=QJsonDocument::fromJson(data).object();
        const QJsonObject item=root.value("platforms").toObject().value("ubuntu-amd64").toObject();
        const QString version=item.value("version").toString();
        const QString filename=item.value("file").toString();
        const QString sha=item.value("sha256").toString();
        const qint64 size=static_cast<qint64>(item.value("size").toDouble());
        const QString expected=QString("MonitorClient-%1-ubuntu-amd64.deb").arg(version);
        const bool architecture=QSysInfo::currentCpuArchitecture()=="x86_64";
        if(!architecture){
            updateStatus->setText("This updater supports Ubuntu x64 only.");reply->deleteLater();return;
        }
        if(!monitorNewerVersion(version,MONITORAPP_VERSION)){
            updateStatus->setText("Up to date (installed "+QString(MONITORAPP_VERSION)+")");
            reply->deleteLater();return;
        }
        if(root.value("schema").toInt()!=1 || filename!=expected ||
           !QRegularExpression("^[a-fA-F0-9]{64}$").match(sha).hasMatch() ||
           size<=0 || size>512LL*1024*1024){
            updateStatus->setText("Invalid update manifest for Ubuntu.");
            reply->deleteLater();return;
        }
        availableUpdateVersion=version;
        availableUpdateFile=filename;
        availableUpdateSha=sha.toLower();
        availableUpdateSize=size;
        availableUpdateUrl=base.resolved(QUrl(filename)).toString();
        updateStatus->setText("Available: "+version+" (installed "+QString(MONITORAPP_VERSION)+")");
        installUpdateButton->setEnabled(true);
        reply->deleteLater();
    });
}
void ConfigDialog::onInstallUpdate(){
    if(availableUpdateUrl.isEmpty())return;
    const QUrl remote(availableUpdateUrl);
    const QUrl base=monitorUpdateBase(updateServerInput->text());
    if(!base.isValid()||remote.host()!=base.host()||
       remote.port()!=base.port()||remote.scheme()!=base.scheme()){
        updateStatus->setText("Update server changed; check again.");return;
    }
    const QString cache=QStandardPaths::writableLocation(QStandardPaths::CacheLocation)+"/updates";
    QDir().mkpath(cache);
    const QString output=cache+"/"+availableUpdateFile;
    auto file=std::make_shared<QSaveFile>(output);
    if(!file->open(QIODevice::WriteOnly)){
        updateStatus->setText("Cannot create update download file.");return;
    }
    auto hash=std::make_shared<QCryptographicHash>(QCryptographicHash::Sha256);
    auto count=std::make_shared<qint64>(0);
    auto writeError=std::make_shared<bool>(false);
    installUpdateButton->setEnabled(false);
    checkUpdateButton->setEnabled(false);
    updateStatus->setText("Downloading "+availableUpdateVersion+"...");
    QNetworkRequest req(remote);
    req.setAttribute(QNetworkRequest::FollowRedirectsAttribute,false);
    auto *reply=updateNetwork->get(req);
    const qint64 expectedSize=availableUpdateSize;
    const QString expectedHash=availableUpdateSha;
    auto drain=[reply,file,hash,count,writeError,expectedSize](){
        const QByteArray bytes=reply->readAll();
        *count+=bytes.size();
        if(*count>expectedSize || file->write(bytes)!=bytes.size()){
            *writeError=true;
            reply->abort();
        }else hash->addData(bytes);
    };
    connect(reply,&QIODevice::readyRead,this,drain);
    connect(reply,&QNetworkReply::finished,this,
        [this,reply,file,hash,count,writeError,expectedHash,expectedSize,output,drain](){
        drain();
        checkUpdateButton->setEnabled(true);
        const int code=reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        if(*writeError || reply->error()!=QNetworkReply::NoError || code!=200 ||
           *count!=expectedSize || QString::fromLatin1(hash->result().toHex())!=expectedHash){
            file->cancelWriting();
            updateStatus->setText("Update download failed or SHA-256 mismatch.");
            reply->deleteLater();return;
        }
        if(!file->commit()){
            updateStatus->setText("Could not save downloaded update.");reply->deleteLater();return;
        }
        reply->deleteLater();
        // pkexec supplies the system authorization prompt. Do not claim success
        // before dpkg has actually installed the package.
        if(!QProcess::startDetached("pkexec",{"/usr/bin/dpkg","-i",output})){
            updateStatus->setText("Cannot start package installer (pkexec required).");
            installUpdateButton->setEnabled(true);return;
        }
        updateStatus->setText("Installer launched. Authorize installation, then restart MonitorClient.");
        QMessageBox::information(this,"Update started",
            "The Ubuntu package installer has been launched. Approve the system prompt. "
            "If it fails, reinstall the saved .deb from your cache.");
        QTimer::singleShot(200,this,[](){QApplication::quit();});
    });
}
