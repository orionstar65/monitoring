#include "ConfigDialog.h"
#include <QApplication>
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
    content->setSpacing(10);
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
    auto divider=[&](){
        auto *line=new QFrame(page);
        line->setFrameShape(QFrame::HLine);
        content->addWidget(line);
    };
    divider();

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
    serverForm->addRow("User No",userNoInput);
    serverForm->addRow("Password",passwordInput);
    testButton=new QPushButton("Sign In",serverGroup);
    connectionStatusLabel=new QLabel("● Not tested",serverGroup);
    serverForm->addRow(testButton,connectionStatusLabel);
    content->addWidget(serverGroup);
    divider();

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
    divider();

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
    divider();

    notificationsCheck=new QCheckBox("Show status notifications",page);
    content->addWidget(notificationsCheck);
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
    activeUserNo=number;assignmentLocked=false;settingsLocked=false;
    rememberToken(token);
    saveSettings();
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
    if(!secret.waitForStarted(1500))return;
    secret.write(token.toUtf8());secret.write("\n");secret.closeWriteChannel();
    if(!secret.waitForFinished(3500)||secret.exitCode()!=0)
        QMessageBox::warning(this,"Keyring","Install and unlock a Secret Service keyring to remember your selected user.");
}
void ConfigDialog::loadRememberedSession(){
    authToken=readRememberedToken();
    if(authToken.isEmpty())return;
    auto state=authRequest({{"action","restore"}},true);
    if(!state.value("success").toBool()){authToken.clear();return;}
    monitorId=state.value("monitor_id").toInt();
    activeUserNo=state.value("user").toObject().value("user_no").toString();
    assignmentLocked=state.value("assignment_locked").toBool();
    // An authenticated session may exist before a monitor is selected.
}
bool ConfigDialog::saveServerIfChanged(){
    if(getServerIP().isEmpty()){QMessageBox::warning(this,"Server","Enter a valid server IP.");return false;}
    QSettings previous(settingsPath(),QSettings::IniFormat);
    const QString oldHost=previous.value("Server/ip","192.168.1.45").toString();
    const int oldPort=previous.value("Server/port",8924).toInt();
    if(oldHost!=getServerIP()||oldPort!=getServerPort()){
        clearStoredToken(oldHost,oldPort);
        authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();
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
        authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();
        updateMonitorList({});updateUI();return;
    }
    monitorId=result.value("monitor_id").toInt();
    assignmentLocked=result.value("assignment_locked").toBool();
    settingsLocked=false;
    activeUserNo=result.value("user").toObject().value("user_no").toString();
    if(userNoInput)userNoInput->setText(activeUserNo);
    updateMonitorList(result.value("monitors").toArray());
}
void ConfigDialog::updateMonitorList(const QJsonArray &items){
    assignedMonitors=items;
    const QSignalBlocker blocked(monitorSelector);
    monitorSelector->clear();
    for(const auto &value:items){
        const QJsonObject m=value.toObject();
        monitorSelector->addItem(QString("%1 · %2-%3").arg(
            m.value("ip_address").toString("—"),m.value("platform").toString("—"),
            m.value("type").toString("Desktop")),
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
            const auto monitor=value.toObject();
            if(monitor.value("id").toInt()==id){
                assignmentLocked=monitor.value("assignment_locked").toBool();
                settingsLocked=monitor.value("settings_locked").toBool();
                break;
            }
        }
        saveSettings();
        emit sessionStateChanged();
    }
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
        settingsLocked=m.value("settings_locked").toBool();
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
    if(!authToken.isEmpty()&&oldHost==getServerIP()&&oldPort==getServerPort())
        authRequest({{"action","logout"}},true);
    clearStoredToken(oldHost,oldPort);
    authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();pendingUserNo.clear();logoutPerformed=true;
    updateMonitorList({});saveSettings();updateUI();
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
        rememberToken(authToken);refreshMonitors();refreshDirectory();saveSettings();updateUI();
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
    saveSettings();refreshMonitors();refreshDirectory();updateUI();
    emit sessionStateChanged();
}
