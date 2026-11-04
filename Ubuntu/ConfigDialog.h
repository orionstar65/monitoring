#ifndef CONFIGDIALOG_H
#define CONFIGDIALOG_H

#include <QDialog>
#include <QLineEdit>
#include <QSpinBox>
#include <QPushButton>
#include <QLabel>
#include <QGroupBox>
#include <QComboBox>
#include <QCheckBox>
#include <QRadioButton>
#include <QButtonGroup>
#include <QStackedWidget>
#include <QJsonArray>
#include <QJsonObject>

class ConfigDialog : public QDialog {
    Q_OBJECT
public:
    explicit ConfigDialog(QWidget *parent=nullptr);
    ~ConfigDialog() override = default;
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
    void onPairClicked();
    void onSwitchClicked();
    void onLogoutClicked();
    void onUserChanged(int);
    void onMonitorChanged(int);
    void onTestConnectionClicked();
    void onLockSettingsClicked();
    void onEditClicked();
private:
    QJsonObject authRequest(const QJsonObject &payload,bool authenticated=false);
    QString settingsPath() const;
    void loadSettings();
    void saveSettings();
    bool saveServerIfChanged();
    void clearStoredToken(const QString &host,int port);
    QString readRememberedToken() const;
    void rememberToken(const QString &value);
    void loadRememberedSession();
    void refreshDirectory();
    void refreshMonitors();
    void updateUI();
    void updateMonitorList(const QJsonArray &items);
    bool testConnection();

    QString authToken;
    QString activeUserNo;
    QString pendingUserNo;
    QString directoryError;
    QJsonArray assignedMonitors;
    int monitorId=0;
    bool assignmentLocked=false;
    bool settingsLocked=false;
    bool monitorEditing=false;
    bool logoutPerformed=false;
    QLineEdit *serverIPEdit=nullptr,*clientNameEdit=nullptr;
    QLineEdit *labelEdit=nullptr,*remarkEdit=nullptr;
    QSpinBox *serverPortEdit=nullptr;
    QComboBox *userSelector=nullptr,*monitorSelector=nullptr;
    QButtonGroup *osRadios=nullptr,*machineRadios=nullptr;
    QLabel *connectionStatusLabel=nullptr,*assignmentStatusLabel=nullptr,*monitorIPLabel=nullptr;
    QLabel *labelInfo=nullptr,*osInfo=nullptr,*machineInfo=nullptr,*remarkInfo=nullptr;
    QStackedWidget *labelStack=nullptr,*osStack=nullptr,*machineStack=nullptr,*remarkStack=nullptr;
    QCheckBox *notificationsCheck=nullptr;
    QGroupBox *monitorGroup=nullptr;
    QPushButton *testButton=nullptr,*logoutButton=nullptr,*saveButton=nullptr,*lockSettingsButton=nullptr,*editButton=nullptr;
};
#endif
