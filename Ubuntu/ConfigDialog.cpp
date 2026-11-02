#include "ConfigDialog.h"
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QMessageBox>
#include <QApplication>
#include <QStandardPaths>
#include <QDir>
#include <QJsonDocument>
#include <QJsonObject>
#include <QJsonArray>
#include <QEventLoop>
#include <QInputDialog>
#include <QProcess>
#include <QSignalBlocker>
#include <QTimer>
#include <QFile>
#include <QFormLayout>

ConfigDialog::ConfigDialog(QWidget *parent)
    : QDialog(parent)
{
    setWindowTitle(QStringLiteral("MonitorClient — Configuration"));
    setMinimumSize(470, 620);
    resize(500, 670);
    setModal(true);

    // Create layout
    QVBoxLayout *mainLayout = new QVBoxLayout(this);

    // Server settings group
    QGroupBox *serverGroup = new QGroupBox("Server Settings");
    QVBoxLayout *serverLayout = new QVBoxLayout(serverGroup);

    // Server IP
    QHBoxLayout *ipLayout = new QHBoxLayout();
    QLabel *ipLabel = new QLabel("Server IP:");
    serverIPEdit = new QLineEdit();
    ipLayout->addWidget(ipLabel);
    ipLayout->addWidget(serverIPEdit);
    serverLayout->addLayout(ipLayout);

    // Server Port
    QHBoxLayout *portLayout = new QHBoxLayout();
    QLabel *portLabel = new QLabel("Server Port:");
    serverPortEdit = new QSpinBox();
    serverPortEdit->setRange(1, 65535);
    serverPortEdit->setValue(8924);
    portLayout->addWidget(portLabel);
    portLayout->addWidget(serverPortEdit);
    serverLayout->addLayout(portLayout);

    // Client Name
    QHBoxLayout *nameLayout = new QHBoxLayout();
    QLabel *nameLabel = new QLabel("Client Name:");
    clientNameEdit = new QLineEdit();
    nameLayout->addWidget(nameLabel);
    nameLayout->addWidget(clientNameEdit);
    serverLayout->addLayout(nameLayout);

    // Test connection button
    testButton = new QPushButton("Test Connection");
    serverLayout->addWidget(testButton);

    connectionStatusLabel=new QLabel("● Not connected / Not configured");
    connectionStatusLabel->setStyleSheet("color:#d32f2f;font-weight:600");
    serverLayout->addWidget(connectionStatusLabel);
    mainLayout->addWidget(serverGroup);

    QGroupBox *authGroup=new QGroupBox("Account");
    QVBoxLayout *authLayout=new QVBoxLayout(authGroup);
    QTabWidget *tabs=new QTabWidget(authGroup);
    QWidget *signInTab=new QWidget(tabs);
    auto *signinLayout=new QVBoxLayout(signInTab);
    loginEdit=new QLineEdit();
    loginEdit->setPlaceholderText("Username or email");
    passwordEdit=new QLineEdit();
    passwordEdit->setEchoMode(QLineEdit::Password);
    passwordEdit->setPlaceholderText("Password");
    signInButton=new QPushButton("Sign In");
    signinLayout->addWidget(loginEdit);
    signinLayout->addWidget(passwordEdit);
    signinLayout->addWidget(signInButton);
    tabs->addTab(signInTab,"Sign In");
    QWidget *signUpTab=new QWidget(tabs);
    auto *signupLayout=new QVBoxLayout(signUpTab);
    signupFullNameEdit=new QLineEdit();signupFullNameEdit->setPlaceholderText("Full Name");
    signupUsernameEdit=new QLineEdit();signupUsernameEdit->setPlaceholderText("Username");
    signupPasswordEdit=new QLineEdit();signupPasswordEdit->setEchoMode(QLineEdit::Password);
    signupPasswordEdit->setPlaceholderText("Password (12+ characters)");
    signupConfirmEdit=new QLineEdit();signupConfirmEdit->setEchoMode(QLineEdit::Password);
    signupConfirmEdit->setPlaceholderText("Confirm password");
    QPushButton *signupButton=new QPushButton("Sign Up");
    signupLayout->addWidget(signupFullNameEdit);
    signupLayout->addWidget(signupUsernameEdit);
    signupLayout->addWidget(signupPasswordEdit);
    signupLayout->addWidget(signupConfirmEdit);
    signupLayout->addWidget(signupButton);
    tabs->addTab(signUpTab,"Sign Up");
    authLayout->addWidget(tabs);
    accountStatusLabel=new QLabel("Not signed in");
    logoutButton=new QPushButton("Log Out");
    logoutButton->setEnabled(false);
    QHBoxLayout *sessionLayout=new QHBoxLayout();
    sessionLayout->addWidget(accountStatusLabel);
    sessionLayout->addWidget(logoutButton);
    authLayout->addLayout(sessionLayout);
    mainLayout->addWidget(authGroup);
    connect(logoutButton,&QPushButton::clicked,this,&ConfigDialog::onLogoutClicked);
    connect(signInButton,&QPushButton::clicked,this,&ConfigDialog::onSignInClicked);
    connect(signupButton,&QPushButton::clicked,this,&ConfigDialog::onSignUpClicked);

    QGroupBox *monitorGroup=new QGroupBox("Monitor Settings");
    auto *monitorLayout=new QFormLayout(monitorGroup);
    monitorSelector=new QComboBox();
    monitorSelector->addItem("Add New...",0);
    labelEdit=new QLineEdit();
    labelEdit->setPlaceholderText("Optional monitor label");
    osSelector=new QComboBox();
    osSelector->addItems({"Windows","Mac","Ubuntu"});
    osSelector->setCurrentText("Ubuntu");
    machineSelector=new QComboBox();
    machineSelector->addItems({"Laptop","Desktop"});
    remarkEdit=new QLineEdit();
    remarkEdit->setPlaceholderText("Remark (optional)");
    monitorLayout->addRow("Monitor",monitorSelector);
    monitorLayout->addRow("Label",labelEdit);
    monitorLayout->addRow("OS",osSelector);
    monitorLayout->addRow("Machine",machineSelector);
    monitorLayout->addRow("Remark",remarkEdit);
    mainLayout->addWidget(monitorGroup);
    notificationsCheck=new QCheckBox("Show connection and status notifications");
    notificationsCheck->setChecked(true);
    mainLayout->addWidget(notificationsCheck);
    connect(monitorSelector,QOverload<int>::of(&QComboBox::currentIndexChanged),
            this,&ConfigDialog::onMonitorChanged);
    // Buttons
    QHBoxLayout *buttonLayout = new QHBoxLayout();
    saveButton = new QPushButton("Save");
    cancelButton = new QPushButton("Cancel");
    buttonLayout->addWidget(saveButton);
    buttonLayout->addWidget(cancelButton);
    mainLayout->addLayout(buttonLayout);

    // Connect signals
    connect(saveButton, &QPushButton::clicked, this, &ConfigDialog::onSaveClicked);
    connect(cancelButton, &QPushButton::clicked, this, &ConfigDialog::onCancelClicked);
    connect(testButton, &QPushButton::clicked, this, &ConfigDialog::onTestConnectionClicked);

    // Load current settings
    loadSettings();
    loadRememberedSession();
}

ConfigDialog::~ConfigDialog()
{
}

QString ConfigDialog::getServerIP() const
{
    return serverIPEdit->text();
}

int ConfigDialog::getServerPort() const
{
    return serverPortEdit->value();
}

QString ConfigDialog::getClientName() const
{
    return clientNameEdit->text();
}

void ConfigDialog::loadSettings()
{
    QString settingsPath = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation) + "/monitorclient/settings.ini";
    if (!QFile::exists(settingsPath)) {
        settingsPath = "settings.ini"; // Fallback to local file
    }

    QSettings settings(settingsPath, QSettings::IniFormat);
    
    serverIPEdit->setText(settings.value("Server/ip", "192.168.1.45").toString());
    serverPortEdit->setValue(settings.value("Server/port", 8924).toInt());
    clientNameEdit->setText(settings.value("Client/name", qgetenv("USER")).toString());
    notificationsCheck->setChecked(settings.value("Notifications/enabled",true).toBool());
}

void ConfigDialog::saveSettings()
{
    QString configDir = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation) + "/monitorclient";
    QDir().mkpath(configDir);
    QString settingsPath = configDir + "/settings.ini";

    QSettings settings(settingsPath, QSettings::IniFormat);
    
    settings.setValue("Server/ip", serverIPEdit->text());
    settings.setValue("Server/port", serverPortEdit->value());
    settings.setValue("Client/name", clientNameEdit->text());
    settings.setValue("Notifications/enabled",notificationsCheck->isChecked());
    settings.setValue("Monitor/id",monitorId);
    
    // Also save to local settings.ini for backward compatibility
    QSettings localSettings("settings.ini", QSettings::IniFormat);
    localSettings.setValue("Server/ip", serverIPEdit->text());
    localSettings.setValue("Server/port", serverPortEdit->value());
    localSettings.setValue("Client/name", clientNameEdit->text());
    localSettings.setValue("Notifications/enabled",notificationsCheck->isChecked());
    localSettings.setValue("Monitor/id",monitorId);
}

bool ConfigDialog::testConnection()
{
    QNetworkAccessManager *manager = new QNetworkAccessManager(this);
    QString url = QString("%1://%2:%3").arg(serverPortEdit->value() == 8924 ? "http" : "https").arg(serverIPEdit->text()).arg(serverPortEdit->value());
    
    QNetworkRequest request(url);
    QNetworkReply *reply = manager->get(request);
    
    QEventLoop loop;
    connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();
    
    bool success = (reply->error() == QNetworkReply::NoError);
    reply->deleteLater();
    manager->deleteLater();
    
    return success;
}

void ConfigDialog::onSaveClicked()
{
    if(authToken.isEmpty()){
        QMessageBox::warning(this,"Sign in required","Use the Sign In or Sign Up tab first.");
        return;
    }
    const int id=monitorSelector->currentData().toInt();
    QJsonObject payload{{"label",labelEdit->text().trimmed()},
                        {"os",osSelector->currentText()},
                        {"machine",machineSelector->currentText().toLower()},
                        {"remark",remarkEdit->text().trimmed()}};
    if(id>0){
        payload["action"]="save_monitor";
        payload["monitor_id"]=id;
    }else{
        payload["action"]="request_monitor";
    }
    const QJsonObject saved=authRequest(payload,true);
    if(!saved.value("success").toBool()){
        QMessageBox::warning(this,"Monitor settings",saved.value("error").toString("Could not save monitor."));
        return;
    }
    const int selectedId=id>0?id:saved.value("monitor_id").toInt();
    if(selectedId<1){
        QMessageBox::warning(this,"Monitor","Registration did not return a monitor ID.");return;
    }
    const QJsonObject selected=authRequest(QJsonObject{{"action","select"},{"monitor_id",selectedId}},true);
    if(!selected.value("success").toBool()){
        QMessageBox::warning(this,"Monitor selection",selected.value("error").toString("Monitor not assigned."));
        return;
    }
    monitorId=selectedId;
    connectionStatusLabel->setText("● Authenticated · monitor #"+QString::number(monitorId));
    connectionStatusLabel->setStyleSheet("color:#1b8c40;font-weight:600");
    saveSettings();
    accept();
}
void ConfigDialog::onCancelClicked()
{
    reject();
}

void ConfigDialog::onTestConnectionClicked()
{
    testButton->setEnabled(false);
    testButton->setText("Testing...");
    
    bool success = testConnection();
    
    testButton->setEnabled(true);
    testButton->setText("Test Connection");
    
    if (success) {
        QMessageBox::information(this, "Success", "Connection test successful!");
    } else {
        QMessageBox::warning(this, "Error", "Connection test failed!\nPlease check server IP and port.");
    }
} 
QJsonObject ConfigDialog::authRequest(const QJsonObject &payload,bool authenticated)
{
    QNetworkAccessManager manager;
    QNetworkRequest req(QUrl(QString("%1://%2:%3/client_auth.php").arg(serverPortEdit->value() == 8924 ? "http" : "https").arg(serverIPEdit->text()).arg(serverPortEdit->value())));
    req.setHeader(QNetworkRequest::ContentTypeHeader,"application/json");
    if(authenticated && !authToken.isEmpty())req.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply=manager.post(req,QJsonDocument(payload).toJson());
    QEventLoop loop;QObject::connect(reply,&QNetworkReply::finished,&loop,&QEventLoop::quit);
    loop.exec();
    QJsonObject response=QJsonDocument::fromJson(reply->readAll()).object();
    if(reply->error()!=QNetworkReply::NoError && !response.contains("error"))response["error"]=reply->errorString();
    reply->deleteLater();return response;
}
void ConfigDialog::onLogoutClicked()
{
    if(!authToken.isEmpty()){
        authRequest(QJsonObject{{"action","logout"}},true);
    }
    QProcess secret;
    const QString address=serverIPEdit->text().trimmed()+":"+QString::number(serverPortEdit->value());
    secret.start("secret-tool",{"clear","service","acc-monitor","host",address});
    secret.waitForFinished(4000);
    authToken.clear();
    monitorId=0;
    assignedMonitors=QJsonArray();
    monitorSelector->clear();
    monitorSelector->addItem("Add New...",0);
    accountStatusLabel->setText("Not signed in");
    logoutButton->setEnabled(false);
    connectionStatusLabel->setText("● Not signed in");
    connectionStatusLabel->setStyleSheet("color:#d32f2f;font-weight:600");
}
void ConfigDialog::onSignInClicked()
{
    const QJsonObject response=authRequest(
        QJsonObject{{"action","login"},{"identifier",loginEdit->text().trimmed()},
                    {"password",passwordEdit->text()}});
    passwordEdit->clear();
    if(!response.value("success").toBool()){
        QMessageBox::warning(this,"Sign in failed",
            response.value("code").toString()=="invalid_credentials"
                ? "Incorrect password or username. Ask your administrator to reset the password if needed."
                : response.value("error").toString("Server unavailable."));
        return;
    }
    authToken=response.value("token").toString();
    accountStatusLabel->setText("Signed in: "+loginEdit->text().trimmed());
    logoutButton->setEnabled(true);
    rememberToken(authToken);
    updateMonitorList(response.value("monitors").toArray());
    QMessageBox::information(this,"Signed in","Choose your monitor below and save.");
}
void ConfigDialog::onSignUpClicked()
{
    if(signupPasswordEdit->text()!=signupConfirmEdit->text()){
        QMessageBox::warning(this,"Password mismatch","Enter the same password twice.");
        return;
    }
    const QJsonObject response=authRequest(
        QJsonObject{{"action","signup"},
                    {"full_name",signupFullNameEdit->text().trimmed()},
                    {"username",signupUsernameEdit->text().trimmed()},
                    {"password",signupPasswordEdit->text()},
                    {"confirm_password",signupConfirmEdit->text()}});
    signupPasswordEdit->clear();signupConfirmEdit->clear();
    if(!response.value("success").toBool()){
        QMessageBox::information(this,"Sign up",
            response.value("code").toString()=="username_exists"
              ? "Username already exists. Use the Sign In tab instead."
              : response.value("error").toString("Unable to create account."));
        return;
    }
    loginEdit->setText(signupUsernameEdit->text().trimmed());
    QMessageBox::information(this,"Account created","Account created. Switch to Sign In to continue.");
}
void ConfigDialog::updateMonitorList(const QJsonArray &monitors)
{
    assignedMonitors=monitors;
    const QSignalBlocker block(monitorSelector);
    monitorSelector->clear();
    for(const QJsonValue &val:monitors){
        const QJsonObject m=val.toObject();
        const int id=m.value("id").toInt();
        monitorSelector->addItem(QString("%1 · %2 · %3 · %4")
        .arg(m.value("name").toString("Monitor"),
             m.value("platform").toString("—"),
             m.value("type").toString("Desktop"),
             m.value("ip_address").toString("—")),id);
    }
    monitorSelector->addItem("Add New...",0);
    QSettings settings(settingsPath(),QSettings::IniFormat);
    const int saved=settings.value("Monitor/id",0).toInt();
    const int index=monitorSelector->findData(saved);
    monitorSelector->setCurrentIndex(index>=0?index:monitorSelector->count()-1);
    onMonitorChanged(monitorSelector->currentIndex());
}
void ConfigDialog::onMonitorChanged(int)
{
    const int id=monitorSelector->currentData().toInt();
    saveButton->setText(id>0?"Save":"Add");
    if(!id){
        labelEdit->clear();remarkEdit->clear();osSelector->setCurrentText("Ubuntu");
        machineSelector->setCurrentText("Laptop");
        return;
    }
    for(const QJsonValue &val:assignedMonitors){
        const QJsonObject m=val.toObject();
        if(m.value("id").toInt()!=id)continue;
        labelEdit->setText(m.value("name").toString());
        remarkEdit->setText(m.value("remark").toString());
        osSelector->setCurrentText(m.value("platform").toString("Ubuntu"));
        const QString type=m.value("type").toString().toLower();
        machineSelector->setCurrentText(type=="desktop"?"Desktop":"Laptop");
        break;
    }
}
QString ConfigDialog::settingsPath() const
{
    return QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient/settings.ini";
}
QString ConfigDialog::readRememberedToken() const
{
    QProcess secret;
    secret.start("secret-tool",{"lookup","service","acc-monitor","host",
         serverIPEdit->text().trimmed()+":"+QString::number(serverPortEdit->value())});
    if(!secret.waitForFinished(3000) || secret.exitCode()!=0)return {};
    return QString::fromUtf8(secret.readAllStandardOutput()).trimmed();
}
void ConfigDialog::rememberToken(const QString &value)
{
    QProcess secret;
    const QString address=serverIPEdit->text().trimmed()+":"+QString::number(serverPortEdit->value());
    secret.start("secret-tool",{"store","--label=ACC Monitor v1.4",
        "service","acc-monitor","host",address});
    if(!secret.waitForStarted(2000))return;
    secret.write(value.toUtf8());secret.write("\n");secret.closeWriteChannel();
    if(!secret.waitForFinished(4000) || secret.exitCode()!=0){
        QMessageBox::warning(this,"Session not saved",
           "Install and unlock a Secret Service keyring (libsecret-tools) to remember your login across restarts.");
    }
}
void ConfigDialog::loadRememberedSession()
{
    authToken=readRememberedToken();
    if(authToken.isEmpty())return;
    const QJsonObject restored=authRequest(QJsonObject{{"action","restore"}},true);
    if(!restored.value("success").toBool()){
        authToken.clear();return;
    }
    accountStatusLabel->setText("Signed in: "+
        restored.value("user").toObject().value("username").toString());
    logoutButton->setEnabled(true);
    updateMonitorList(restored.value("monitors").toArray());
    const int saved=restored.value("monitor_id").toInt();
    if(saved>0){
        monitorId=saved;
        const int index=monitorSelector->findData(saved);
        if(index>=0)monitorSelector->setCurrentIndex(index);
    }
}
void ConfigDialog::onRequestMonitorClicked()
{
    if(authToken.isEmpty()){QMessageBox::information(this,"Sign in first","Please sign in before requesting a monitor.");return;}
    bool ok=false;const QString name=QInputDialog::getText(this,"Request monitor","Device name:",QLineEdit::Normal,QString(),&ok);
    if(!ok||name.trimmed().isEmpty())return;
    QJsonObject response=authRequest(QJsonObject{{"action","request_monitor"},{"name",name.trimmed()},
        {"os","Ubuntu"},{"machine","laptop"}},true);
    if(response.value("success").toBool()){
        monitorId=response.value("monitor_id").toInt();
        QJsonObject all=authRequest(QJsonObject{{"action","monitors"}},true);
        updateMonitorList(all.value("monitors").toArray());
        int ix=monitorSelector->findData(monitorId);
        if(ix>=0)monitorSelector->setCurrentIndex(ix);
    }
    QMessageBox::information(this,"Monitor registration",response.value("success").toBool()
        ?"Monitor created and assigned to your account.":response.value("error").toString("Registration failed"));
}
