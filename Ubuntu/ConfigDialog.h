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

private slots:
    void onSaveClicked();
    void onSignInClicked();
    void onRequestMonitorClicked();
    void onCancelClicked();
    void onTestConnectionClicked();

private:
    void loadSettings();
    void saveSettings();
    bool testConnection();
    QJsonObject authRequest(const QJsonObject &payload, bool authenticated = false);
    QString authToken;
    int monitorId = 0;
    QLineEdit *loginEdit;
    QLineEdit *passwordEdit;
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