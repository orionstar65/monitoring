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

ConfigDialog::ConfigDialog(QWidget *parent) : QDialog(parent) {
    setWindowTitle("MonitorClient v1.6 — Settings");
    setMinimumSize(470,420);
    resize(520,700);
    setModal(true);
    auto *outer=new QVBoxLayout(this);
    auto *scroll=new QScrollArea(this);
    scroll->setWidgetResizable(true);
    scroll->setFrameShape(QFrame::NoFrame);
    auto *page=new QWidget(scroll);
    auto *content=new QVBoxLayout(page);
    content->setSpacing(12);
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
    brand->addWidget(name);brand->addWidget(new QLabel("v1.6"));
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
    testButton=new QPushButton("Test Connection",serverGroup);
    connectionStatusLabel=new QLabel("● Not tested",serverGroup);
    serverForm->addRow(testButton,connectionStatusLabel);
    content->addWidget(serverGroup);
    divider();

    auto *userGroup=new QWidget(page);
    auto *userForm=new QFormLayout(userGroup);
    userSelector=new QComboBox(userGroup);
    userSelector->setMinimumWidth(350);
    userForm->addRow("User:",userSelector);
    logoutButton=new QPushButton("Disconnect",userGroup);
    userForm->addRow("",logoutButton);
    assignmentStatusLabel=new QLabel(userGroup);
    assignmentStatusLabel->setWordWrap(true);
    userForm->addRow("",assignmentStatusLabel);
    content->addWidget(userGroup);
    divider();

    monitorGroup=new QGroupBox("Monitor Settings",page);
    auto *monitorForm=new QFormLayout(monitorGroup);
    monitorSelector=new QComboBox(monitorGroup);
    labelEdit=new QLineEdit(monitorGroup);
    osSelector=new QComboBox(monitorGroup);osSelector->addItems({"Windows","Mac","Ubuntu"});
    machineSelector=new QComboBox(monitorGroup);machineSelector->addItems({"Laptop","Desktop"});
    monitorIPLabel=new QLabel("—",monitorGroup);
    remarkEdit=new QLineEdit(monitorGroup);
    clientNameEdit=labelEdit;
    monitorForm->addRow("Monitor",monitorSelector);
    monitorForm->addRow("Label",labelEdit);
    monitorForm->addRow("OS",osSelector);
    monitorForm->addRow("Machine",machineSelector);
    monitorForm->addRow("IP address",monitorIPLabel);
    monitorForm->addRow("Remark",remarkEdit);
    content->addWidget(monitorGroup);
    divider();

    notificationsCheck=new QCheckBox("Show status notifications",page);
    content->addWidget(notificationsCheck);
    auto *buttons=new QHBoxLayout();
    saveButton=new QPushButton("Save",this);
    auto *closeButton=new QPushButton("Close",this);
    buttons->addStretch();buttons->addWidget(saveButton);buttons->addWidget(closeButton);
    outer->addLayout(buttons);
    connect(testButton,&QPushButton::clicked,this,&ConfigDialog::onTestConnectionClicked);
    connect(logoutButton,&QPushButton::clicked,this,&ConfigDialog::onLogoutClicked);
    connect(userSelector,QOverload<int>::of(&QComboBox::currentIndexChanged),this,&ConfigDialog::onUserChanged);
    connect(monitorSelector,QOverload<int>::of(&QComboBox::currentIndexChanged),this,&ConfigDialog::onMonitorChanged);
    connect(saveButton,&QPushButton::clicked,this,&ConfigDialog::onSaveClicked);
    connect(closeButton,&QPushButton::clicked,this,&ConfigDialog::reject);
    loadSettings();
    loadRememberedSession();
    refreshDirectory();
    refreshMonitors();
    updateUI();
}
QString ConfigDialog::settingsPath() const {
    return QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient/settings.ini";
}
QString ConfigDialog::getServerIP() const {return serverIPEdit->text().trimmed();}
int ConfigDialog::getServerPort() const {return serverPortEdit->value();}
QString ConfigDialog::getClientName() const {return clientNameEdit->text().trimmed();}
void ConfigDialog::loadSettings(){
    QString path=settingsPath();
    if(!QFile::exists(path))path="settings.ini";
    QSettings cfg(path,QSettings::IniFormat);
    serverIPEdit->setText(cfg.value("Server/ip","192.168.1.45").toString());
    serverPortEdit->setValue(cfg.value("Server/port",8924).toInt());
    labelEdit->setText(cfg.value("Client/name","Monitor").toString());
    notificationsCheck->setChecked(cfg.value("Notifications/enabled",true).toBool());
}
void ConfigDialog::saveSettings(){
    QDir().mkpath(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient");
    for(const QString &path:{settingsPath(),QStringLiteral("settings.ini")}){
        QSettings cfg(path,QSettings::IniFormat);
        cfg.setValue("Server/ip",getServerIP());
        cfg.setValue("Server/port",getServerPort());
        cfg.setValue("Client/name",labelEdit->text());
        cfg.setValue("Notifications/enabled",notificationsCheck->isChecked());
        cfg.setValue("Monitor/id",monitorId);
        cfg.setValue("User/no",userSelector->currentData().toString());
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
void ConfigDialog::onTestConnectionClicked(){
    testButton->setEnabled(false);
    connectionStatusLabel->setText("● Testing...");
    const bool ok=testConnection();
    testButton->setEnabled(true);
    connectionStatusLabel->setText(ok?"● Server reachable":"● Server unavailable (check IP, port and TLS)");
    connectionStatusLabel->setStyleSheet(ok?"color:#16803c":"color:#d32f2f");
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
    secret.start("secret-tool",{"store","--label=MonitorClient v1.6 device",
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
    if(monitorId<1)authToken.clear();
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
    const QString current=authToken.isEmpty()?
        QSettings(settingsPath(),QSettings::IniFormat).value("User/no").toString()
        : userSelector->currentData().toString();
    userSelector->clear();
    userSelector->addItem("Select a user…",QString());
    for(auto entry:result.value("users").toArray()){
        const auto user=entry.toObject();
        const QString number=user.value("user_no").toString();
        if(number.isEmpty())continue;
        const QString label=number+" / "+user.value("full_name").toString()+
            " / "+user.value("ip_address").toString("—");
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
    activeUserNo=result.value("user").toObject().value("user_no").toString();
    const QSignalBlocker blocked(userSelector);
    const int ix=userSelector->findData(activeUserNo);
    userSelector->setCurrentIndex(ix>=0?ix:0);
    updateMonitorList(result.value("monitors").toArray());
}
void ConfigDialog::updateMonitorList(const QJsonArray &items){
    assignedMonitors=items;
    const QSignalBlocker blocked(monitorSelector);
    monitorSelector->clear();
    for(const auto &value:items){
        const QJsonObject m=value.toObject();
        monitorSelector->addItem(QString("%1 · %2 · %3 · %4").arg(
            m.value("name").toString("Monitor"),m.value("platform").toString("—"),
            m.value("type").toString("Desktop"),m.value("ip_address").toString("—")),
            m.value("id").toInt());
    }
    const int ix=monitorSelector->findData(monitorId);
    monitorSelector->setCurrentIndex(ix>=0?ix:(monitorSelector->count()?0:-1));
    onMonitorChanged(monitorSelector->currentIndex());
}
void ConfigDialog::onMonitorChanged(int){
    const int id=monitorSelector->currentData().toInt();
    if(id>0 && !authToken.isEmpty() && id!=monitorId){
        const auto response=authRequest({{"action","select"},{"monitor_id",id}},true);
        if(!response.value("success").toBool()){
            QMessageBox::warning(this,"Monitor selection",
                response.value("error").toString("Cannot select this monitor."));
            refreshMonitors();
            return;
        }
        monitorId=id;
        for(const auto &value:assignedMonitors){
            const auto monitor=value.toObject();
            if(monitor.value("id").toInt()==id){
                assignmentLocked=monitor.value("assignment_locked").toBool();
                break;
            }
        }
        saveSettings();
        emit sessionStateChanged();
    }
    for(const auto &value:assignedMonitors){
        const QJsonObject m=value.toObject();
        if(m.value("id").toInt()!=id)continue;
        labelEdit->setText(m.value("name").toString());
        remarkEdit->setText(m.value("remark").toString());
        osSelector->setCurrentText(m.value("platform").toString("Ubuntu"));
        machineSelector->setCurrentText(m.value("type").toString().toLower()=="desktop"?"Desktop":"Laptop");
        monitorIPLabel->setText(m.value("ip_address").toString("—"));
        break;
    }
    saveButton->setEnabled(authToken.isEmpty()||id==monitorId);
    if(id!=monitorId && monitorId>0)
        assignmentStatusLabel->setText("Select one of the assigned monitors.");
    else
        updateUI();
}
void ConfigDialog::onUserChanged(int){
    const QString number=userSelector->currentData().toString();
    if(number.isEmpty()||number==activeUserNo)return;
    if(assignmentLocked&&!authToken.isEmpty()){
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
    const bool enrolled=!authToken.isEmpty()&&monitorId>0;
    monitorGroup->setVisible(enrolled);
    logoutButton->setVisible(enrolled);
    logoutButton->setEnabled(enrolled&&!assignmentLocked);
    userSelector->setEnabled(!enrolled||!assignmentLocked);
    assignmentStatusLabel->setVisible(enrolled&&assignmentLocked);
    if(enrolled&&assignmentLocked)
        assignmentStatusLabel->setText("User selection is locked. Ask your administrator to edit.");
    saveButton->setText(enrolled?"Save Monitor":"Save Server");
    saveButton->setEnabled(true);
}
void ConfigDialog::onPairClicked(){
    if(!saveServerIfChanged())return;
    const QString number=userSelector->currentData().toString();
    if(number.isEmpty()){QMessageBox::warning(this,"Select User No","Choose a User No.");return;}
    const auto result=authRequest({{"action","enroll"},{"user_no",number}});
    if(!result.value("success").toBool()){
        QMessageBox::warning(this,"User selection failed",result.value("error").toString("Could not load assigned monitors."));
        return;
    }
    authToken=result.value("token").toString();
    monitorId=result.value("monitor_id").toInt();
    assignmentLocked=result.value("assignment_locked").toBool();
    activeUserNo=number;
    if(authToken.length()!=64||monitorId<1){
        authToken.clear();monitorId=0;
        QMessageBox::warning(this,"User selection failed","Invalid session details returned.");return;
    }
    rememberToken(authToken);
    refreshMonitors();saveSettings();updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onSwitchClicked(){
    if(!saveServerIfChanged())return;
    if(assignmentLocked){QMessageBox::warning(this,"Assignment locked","Ask your administrator to edit this monitor.");return;}
    const auto result=authRequest({{"action","switch_user"},{"user_no",userSelector->currentData().toString()}},true);
    if(!result.value("success").toBool()){
        QMessageBox::warning(this,"Cannot change user",result.value("error").toString("Ask your administrator to change the assignment."));
        refreshMonitors();updateUI();return;
    }
    authToken=result.value("token").toString();
    activeUserNo=userSelector->currentData().toString();
    rememberToken(authToken);
    refreshMonitors();saveSettings();updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onLogoutClicked(){
    if(assignmentLocked){
        QMessageBox::warning(this,"Assignment locked","Ask your administrator to edit this monitor.");
        return;
    }
    QSettings previous(settingsPath(),QSettings::IniFormat);
    const QString oldHost=previous.value("Server/ip","192.168.1.45").toString();
    const int oldPort=previous.value("Server/port",8924).toInt();
    if(!authToken.isEmpty()&&oldHost==getServerIP()&&oldPort==getServerPort())
        authRequest({{"action","logout"}},true);
    clearStoredToken(oldHost,oldPort);
    authToken.clear();monitorId=0;assignmentLocked=false;activeUserNo.clear();logoutPerformed=true;
    updateMonitorList({});saveSettings();updateUI();
    emit sessionStateChanged();
}
void ConfigDialog::onSaveClicked(){
    if(!saveServerIfChanged())return;
    if(authToken.isEmpty()){connectionStatusLabel->setText("● Server saved · select User No");return;}
    const int id=monitorSelector->currentData().toInt();
    if(id<1||id!=monitorId){
        QMessageBox::warning(this,"Monitor","Select an assigned monitor.");
        return;
    }
    const auto result=authRequest({{"action","save_monitor"},{"monitor_id",id},
        {"label",labelEdit->text().trimmed()},{"remark",remarkEdit->text().trimmed()},
        {"os",osSelector->currentText()},{"machine",machineSelector->currentText().toLower()}},true);
    if(!result.value("success").toBool()){
        QMessageBox::warning(this,"Monitor",result.value("error").toString("Could not save monitor."));return;
    }
    saveSettings();refreshMonitors();accept();
}
