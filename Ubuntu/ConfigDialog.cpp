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

ConfigDialog::ConfigDialog(QWidget *parent)
    : QDialog(parent)
{
    setWindowTitle(QStringLiteral("MonitorClient — Configuration"));
    setMinimumSize(440, 320);
    resize(460, 340);
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

    mainLayout->addWidget(serverGroup);

    QGroupBox *authGroup=new QGroupBox("Sign in and select an assigned monitor");
    QVBoxLayout *authLayout=new QVBoxLayout(authGroup);
    loginEdit=new QLineEdit();loginEdit->setPlaceholderText("Email or username");
    passwordEdit=new QLineEdit();passwordEdit->setEchoMode(QLineEdit::Password);passwordEdit->setPlaceholderText("Password");
    monitorSelector=new QComboBox();monitorSelector->addItem("Sign in to load your monitors",0);
    signInButton=new QPushButton("Sign in");
    requestMonitorButton=new QPushButton("Request new monitor (admin approval)");
    authLayout->addWidget(loginEdit);authLayout->addWidget(passwordEdit);
    authLayout->addWidget(signInButton);authLayout->addWidget(monitorSelector);authLayout->addWidget(requestMonitorButton);
    mainLayout->addWidget(authGroup);
    connect(signInButton,&QPushButton::clicked,this,&ConfigDialog::onSignInClicked);
    connect(requestMonitorButton,&QPushButton::clicked,this,&ConfigDialog::onRequestMonitorClicked);
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
    
    // Also save to local settings.ini for backward compatibility
    QSettings localSettings("settings.ini", QSettings::IniFormat);
    localSettings.setValue("Server/ip", serverIPEdit->text());
    localSettings.setValue("Server/port", serverPortEdit->value());
    localSettings.setValue("Client/name", clientNameEdit->text());
}

bool ConfigDialog::testConnection()
{
    QNetworkAccessManager *manager = new QNetworkAccessManager(this);
    QString url = QString("https://%1:%2").arg(serverIPEdit->text()).arg(serverPortEdit->value());
    
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
    if (serverIPEdit->text().isEmpty()) {
        QMessageBox::warning(this, "Error", "Server IP cannot be empty!");
        return;
    }
    
    if (authToken.isEmpty() || monitorSelector->currentData().toInt()<1) {
        QMessageBox::warning(this,"Sign in required","Sign in and select a monitor assigned to you.");return;
    }
    const int selected=monitorSelector->currentData().toInt();
    QJsonObject response=authRequest(QJsonObject{{"action","select"},{"monitor_id",selected}},true);
    if (!response.value("success").toBool()) {
        QMessageBox::warning(this,"Monitor not assigned",response.value("error").toString("Selection failed"));return;
    }
    monitorId=selected;
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
    QNetworkRequest req(QUrl(QString("https://%1:%2/client_auth.php").arg(serverIPEdit->text()).arg(serverPortEdit->value())));
    req.setHeader(QNetworkRequest::ContentTypeHeader,"application/json");
    if(authenticated && !authToken.isEmpty())req.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply=manager.post(req,QJsonDocument(payload).toJson());
    QEventLoop loop;QObject::connect(reply,&QNetworkReply::finished,&loop,&QEventLoop::quit);
    loop.exec();
    QJsonObject response=QJsonDocument::fromJson(reply->readAll()).object();
    if(reply->error()!=QNetworkReply::NoError && !response.contains("error"))response["error"]=reply->errorString();
    reply->deleteLater();return response;
}
void ConfigDialog::onSignInClicked()
{
    authToken.clear();monitorId=0;
    QJsonObject response=authRequest(QJsonObject{{"action","login"},{"identifier",loginEdit->text().trimmed()},{"password",passwordEdit->text()}});
    passwordEdit->clear();
    if(!response.value("success").toBool()){
        QMessageBox::warning(this,"Sign in failed",response.value("error").toString("Check server and credentials."));return;
    }
    authToken=response.value("token").toString();
    monitorSelector->clear();
    for(auto item:response.value("monitors").toArray()){
        auto monitor=item.toObject();
        monitorSelector->addItem(QString("%1 (#%2)").arg(monitor.value("name").toString()).arg(monitor.value("id").toInt()),
                                 monitor.value("id").toInt());
    }
    if(monitorSelector->count()==0)monitorSelector->addItem("No assigned monitors — request one or contact admin",0);
}
void ConfigDialog::onRequestMonitorClicked()
{
    if(authToken.isEmpty()){QMessageBox::information(this,"Sign in first","Please sign in before requesting a monitor.");return;}
    bool ok=false;const QString name=QInputDialog::getText(this,"Request monitor","Device name:",QLineEdit::Normal,QString(),&ok);
    if(!ok||name.trimmed().isEmpty())return;
    QJsonObject response=authRequest(QJsonObject{{"action","request_monitor"},{"name",name.trimmed()}},true);
    QMessageBox::information(this,"Monitor request",response.value("success").toBool()
        ?"Request submitted. Your admin must assign it before selection.":response.value("error").toString("Request failed"));
}
