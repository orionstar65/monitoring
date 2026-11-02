#ifndef CONFIGDIALOG_H
#define CONFIGDIALOG_H

#include <QDialog>
#include <QLineEdit>
#include <QSpinBox>
#include <QPushButton>
#include <QVBoxLayout>
#include <QHBoxLayout>
#include <QLabel>
#include <QGroupBox>
#include <QSettings>
#include <QComboBox>
#include <QJsonObject>
#include <QTabWidget>
#include <QCheckBox>
#include <QJsonArray>
#include <QScrollArea>

class ConfigDialog : public QDialog
{
    Q_OBJECT

public:
    ConfigDialog(QWidget *parent = nullptr);
    ~ConfigDialog();

    QString getServerIP() const;
    int getServerPort() const;
    QString getClientName() const;
    QString getAuthToken() const { return authToken; }
    int getMonitorId() const { return monitorId; }
    bool hasSession() const { return !authToken.isEmpty(); }
    bool notificationsEnabled() const { return notificationsCheck->isChecked(); }
    bool didLogout() const { return logoutPerformed; }

signals:
    void sessionStateChanged();

private slots:
    void onSaveClicked();
    void onSignInClicked();
    void onLogoutClicked();
    void onRequestMonitorClicked();
    void onSignUpClicked();
    void onMonitorChanged(int index);
    void onCancelClicked();
    void onTestConnectionClicked();

private:
    void updateAccountUI();
    void loadSettings();
    void saveSettings();
    bool testConnection();
    QJsonObject authRequest(const QJsonObject &payload, bool authenticated = false);
    void updateMonitorList(const QJsonArray &monitors);
    QString settingsPath() const;
    void loadRememberedSession();
    void rememberToken(const QString &value);
    QString readRememberedToken() const;
    QString authToken;
    QJsonArray assignedMonitors;
    int monitorId = 0;
    bool logoutPerformed = false;
    QGroupBox *monitorGroup = nullptr;
    QTabWidget *accountTabs = nullptr;
    QLineEdit *loginEdit;
    QLineEdit *passwordEdit;
    QLineEdit *signupFullNameEdit;
    QLineEdit *signupUsernameEdit;
    QLineEdit *signupPasswordEdit;
    QLineEdit *signupConfirmEdit;
    QLineEdit *labelEdit;
    QLineEdit *remarkEdit;
    QComboBox *osSelector;
    QComboBox *machineSelector;
    QCheckBox *notificationsCheck;
    QLabel *accountStatusLabel;
    QLabel *connectionStatusLabel;
    QLabel *monitorIPLabel;
    QPushButton *logoutButton;
    QComboBox *monitorSelector;
    QPushButton *signInButton;
    QPushButton *requestMonitorButton;

    QLineEdit *serverIPEdit;
    QSpinBox *serverPortEdit;
    QLineEdit *clientNameEdit;
    QPushButton *testButton;
    QPushButton *saveButton;
    QPushButton *cancelButton;
};

#endif // CONFIGDIALOG_H 