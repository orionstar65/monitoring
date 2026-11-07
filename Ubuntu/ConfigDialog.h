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
#include <QNetworkAccessManager>

class QCloseEvent;

class ConfigDialog : public QDialog {
    Q_OBJECT
public:
    explicit ConfigDialog(QWidget *parent=nullptr);
    ~ConfigDialog() override = default;
    QString getServerIP() const;
    int getServerPort() const;
    QString getClientName() const;
    QString getActiveUserNo() const { return authToken.isEmpty() ? QString() : activeUserNo; }
    // Display-only identity survives transient offline/keyring failures;
    // never treat this value as proof of an authenticated session.
    QString getRememberedUserNo() const { return (authToken.isEmpty() || activeUserNo.isEmpty()) ? rememberedUserNo : activeUserNo; }
    QString getActiveUserName() const { return authToken.isEmpty() ? QString() : activeUserName; }
    QString getAuthToken() const { return authToken; }
    int getMonitorId() const { return monitorId; }
    bool hasSession() const { return !authToken.isEmpty(); }
    bool notificationsEnabled() const { return notificationsCheck->isChecked(); }
    bool didLogout() const { return logoutPerformed; }

protected:
    // Closing Settings never ends the tray application's lifetime.
    void closeEvent(QCloseEvent *event) override;
    void reject() override;

signals:
    void sessionStateChanged();
    void sendAllRequested();
    void refreshConfigurationRequested();

private slots:
    void onSaveClicked();
    void onPairClicked();
    void onSwitchClicked();
    void onLogoutClicked();
    void onUserChanged(int);
    void onMonitorChanged(int);
    void onTestConnectionClicked();
    void onSignInClicked();
    void onLockSettingsClicked();
    void onEditClicked();
    void onCheckUpdate();
    void onInstallUpdate();
private:
    QJsonObject authRequest(const QJsonObject &payload,bool authenticated=false);
    QString settingsPath() const;
    void loadSettings();
    void saveSettings();
    void persistSessionIdentity();
    void clearSessionIdentity();
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
    QString activeUserName;
    QString rememberedUserNo;
    int rememberedMonitorId=0;
    QString pendingUserNo;
    QString directoryError;
    QJsonArray assignedMonitors;
    int monitorId=0;
    bool assignmentLocked=false;
    bool settingsLocked=false;
    bool monitorEditing=false;
    bool logoutPerformed=false;
    QLineEdit *userNoInput=nullptr,*passwordInput=nullptr;
    QLineEdit *serverIPEdit=nullptr;
    QLineEdit *remarkEdit=nullptr;
    QSpinBox *serverPortEdit=nullptr;
    QComboBox *userSelector=nullptr,*monitorSelector=nullptr;
    QButtonGroup *osRadios=nullptr,*machineRadios=nullptr;
    QLabel *connectionStatusLabel=nullptr,*assignmentStatusLabel=nullptr,*monitorIPLabel=nullptr;
    QLabel *userNoFieldLabel=nullptr,*passwordFieldLabel=nullptr,*accountFieldLabel=nullptr,*accountInfoLabel=nullptr;
    QLabel *osInfo=nullptr,*machineInfo=nullptr,*remarkInfo=nullptr;
    QStackedWidget *osStack=nullptr,*machineStack=nullptr,*remarkStack=nullptr;
    QCheckBox *notificationsCheck=nullptr;
    QGroupBox *monitorGroup=nullptr;
    QPushButton *testButton=nullptr,*logoutButton=nullptr,*saveButton=nullptr,*lockSettingsButton=nullptr,*editButton=nullptr;
    QPushButton *sendAllButton=nullptr,*refreshConfigButton=nullptr;
    QLineEdit *updateServerInput=nullptr;
    QLabel *updateStatus=nullptr;
    QPushButton *checkUpdateButton=nullptr,*installUpdateButton=nullptr;
    QNetworkAccessManager *updateNetwork=nullptr;
    QString availableUpdateVersion,availableUpdateFile,availableUpdateSha,availableUpdateUrl;
    qint64 availableUpdateSize=0;
};
#endif
