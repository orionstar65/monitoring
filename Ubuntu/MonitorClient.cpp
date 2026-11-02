#include <QtCore/QDebug>
#include <QtCore/QDateTime>
#include <QtCore/QDir>
#include <QtCore/QProcess>
#include <QtCore/QSettings>
#include <QtCore/QUuid>
#include <QtCore/QStandardPaths>
#include <QtCore/QEventLoop>
#include <QtCore/QTimeZone>
#include <QtCore/QStorageInfo>

#include <QtGui/QScreen>
#include <QtGui/QPixmap>

#include <QtWidgets/QDesktopWidget>

#include <QtNetwork/QNetworkAccessManager>
#include <QtNetwork/QNetworkReply>
#include <QtNetwork/QHttpPart>
#include <QtNetwork/QHttpMultiPart>

#include <QtConcurrent/QtConcurrent>
#include <QFutureWatcher>

#include <QtDBus/QDBusInterface>
#include <QtDBus/QDBusReply>
#include <QtDBus/QDBusConnection>

#include <QtSql/QSqlDatabase>
#include <QtSql/QSqlQuery>
#include <QtSql/QSqlError>
#include <QtSql/QSqlRecord>

#include <QtNetwork/QNetworkInterface>

// #include "utils/desktopinfo.h"
#include "MonitorClient.h"
#include "ConfigDialog.h"
#include <QStandardPaths>
#include <QPainter>
#include <QPen>
#include <QApplication>

#include "functions.h"
#include "version.h"
#include "ConfigDialog.h"
#include <libudev.h>
#include <qarraydata.h>
#include <qchar.h>
#include <qdatetime.h>
#include <qdebug.h>
#include <qglobal.h>
#include <qjsonobject.h>
#include <QByteArray>

#define TIC_INTERVAL 30
#define HISTORY_INTERVAL 120
#define KEY_INTERVAL 60
// #define USB_INTERVAL 60
#define SCREEN_INTERVAL 10
#define STORAGE_CHECK_INTERVAL 5

namespace {

bool monitorClientKeyLogVerbose()
{
    const QByteArray v = qgetenv("MONITORCLIENT_KEYLOG_DEBUG");
    return !v.isEmpty() && v != QByteArrayLiteral("0") && v != QByteArrayLiteral("false")
           && v != QByteArrayLiteral("no");
}

} // namespace

MonitorClient::MonitorClient()
{
    // Load settings first
    loadSettings("settings.ini");
    
    macAddress = getMacAddress();
    lastBrowserTic = -1;
    
    // Initialize configuration intervals with defaults
    screenInterval = 10;  // Ubuntu default (no screenshot by default)
    ticInterval = TIC_INTERVAL;
    historyInterval = HISTORY_INTERVAL;
    keyInterval = KEY_INTERVAL;
    usbInterval = 30;
    configRefreshInterval = 300;
    
    // Initialize configuration management
    isConfigUpdating = false;
    configTimer = new QTimer(this);
    connect(configTimer, &QTimer::timeout, this, [this]() { fetchConfigurationFromAPI(false); });

    // Initialize network manager early so fetchConfigurationFromAPI can use it
    networkManager = new QNetworkAccessManager(this);
    
    // Debug: Print loaded settings
    qDebug() << "Loaded Server IP:" << getServerIP();
    qDebug() << "Loaded Server Port:" << getServerPort();
    qDebug() << "Loaded Client Name:" << getClientName();
    qDebug() << "Local IP Address:" << getLocalIPAddress();
    qDebug() << "MAC Address:" << macAddress;
    // Initialize keyboard monitor
    keyboardMonitor = new KeyboardMonitor(this);
    connect(keyboardMonitor, &KeyboardMonitor::keyPressed, this, &MonitorClient::handleKeyPress);
    // Do not restart keyboard capture on every USB hotplug — that tears down /dev/input fds and loses keys.
    // Keyboard devices are discovered at startup; restart the app after plugging a USB keyboard if needed.
    
    // Connect signals to their respective slots
    // connect(this, &MonitorClient::screen, this, &MonitorClient::getScreen);
    // connect(this, &MonitorClient::tic, this, &MonitorClient::sendTic);
    // connect(this, &MonitorClient::browserHistory, this, &MonitorClient::sendBrowserHistories);
    // connect(this, &MonitorClient::keyLog, this, &MonitorClient::sendKeyLogs);
    // connect(this, &MonitorClient::usbLog, this, &MonitorClient::sendUSBLogs);
    
    // Capture begins only after the user signs in and selects an assigned monitor.

    // Initialize storage monitoring
    getStorageDevices();

    // Initialize USB monitoring
    setupUsbMonitoring();
    
    // Initialize system tray
    setupSystemTray();

    const qint64 now = QDateTime::currentSecsSinceEpoch();
    m_lastEmitScreenshotTime = m_lastEmitKeyLogTime = m_lastEmitUsbLogTime = now;
    m_lastEmitTicTime = 0;
    m_lastEmitHistoryTime = now;

    monitorTimer = new QTimer(this);
    monitorTimer->setInterval(1000);
    connect(monitorTimer, &QTimer::timeout, this, &MonitorClient::monitorTimerTick);
    QTimer::singleShot(1000, this, [this]() { monitorTimer->start(); });
    
    // Restore the revocable session from the Secret Service keyring.
    // If no valid session exists, show the sign-in / sign-up dialog immediately.
    QTimer::singleShot(0,this,[this]() {
        ConfigDialog saved;
        authToken=saved.getAuthToken();
        monitorId=saved.getMonitorId();
        if(authToken.isEmpty() || monitorId<1)onConfigureAction();
        else {
            clientName=saved.getClientName();
            if(keyboardMonitor)keyboardMonitor->startMonitoring();
            scheduleConnectionTest("Checking remembered session...");
        }
        updateTrayIcon();
    });
    // Fetch configuration from API
    fetchConfigurationFromAPI();
    
    // Initialize connection status
    serverConnected = false;
    lastError = "";
    clientName = getClientName();
    
    // Initialize status tracking
    screenshotEnabled = true;
    keylogEnabled = true;
    browserHistoryEnabled = true;
    usbMonitoringEnabled = true;
    screenshotInProgress = false;
    
    // Initialize last sent times
    lastKeySentTime = QDateTime();
    lastUsbSentTime = QDateTime();
    lastBrowserSentTime = QDateTime();
    lastScreenshotSentTime = QDateTime();
    lastTicSentTime = QDateTime();

    // Initialize connection error tracking
    connectionErrorNotified = false;
    m_browserHistoryBusy = false;

    // Initialize Wayland screen capture if running under Wayland
    screenCastPortal = nullptr;
    if (!qgetenv("WAYLAND_DISPLAY").isEmpty()) {
        qDebug() << "Wayland detected, initializing portal screen capture";
        screenCastPortal = new ScreenCastPortal(this);
        connect(screenCastPortal, &ScreenCastPortal::streamReady, this, [this]() {
            qDebug() << "PipeWire screen capture stream is ready";
        });
        connect(screenCastPortal, &ScreenCastPortal::streamFailed, this, [this](const QString &err) {
            qWarning() << "PipeWire screen capture failed:" << err
                       << "- will fall back to GNOME D-Bus method";
        });
        screenCastPortal->start();
    } else {
        qDebug() << "X11 detected, using GNOME D-Bus screenshot method";
    }
}

MonitorClient::~MonitorClient()
{
    if (monitorTimer)
        monitorTimer->stop();
    if (screenCastPortal) {
        screenCastPortal->stop();
    }
    if (keyboardMonitor) {
        keyboardMonitor->stopMonitoring();
        delete keyboardMonitor;
        keyboardMonitor = nullptr;
    }
    cleanupUsbMonitoring();
    cleanupSystemTray();
}

QString MonitorClient::getMacAddress() {
    QString mac;
    
    QList<QNetworkInterface> interfaces = QNetworkInterface::allInterfaces();
    
    for (const QNetworkInterface& interface : interfaces) {
        // Skip loopback and down interfaces
        if (interface.flags().testFlag(QNetworkInterface::IsLoopBack) ||
            !interface.flags().testFlag(QNetworkInterface::IsUp) ||
            !interface.flags().testFlag(QNetworkInterface::IsRunning)) {
            continue;
        }
        
        // Get the first valid MAC address
        if (!interface.hardwareAddress().isEmpty()) {
            mac = interface.hardwareAddress();
            break;
        }
    }
    
    return mac;
}

void MonitorClient::setupUsbMonitoring()
{
    udev = udev_new();
    if (!udev) {
        qDebug() << "Failed to create udev context";
        return;
    }

    monitor = udev_monitor_new_from_netlink(udev, "udev");
    if (!monitor) {
        qDebug() << "Failed to create udev monitor";
        udev_unref(udev);
        return;
    }

    // Filter for USB devices
    udev_monitor_filter_add_match_subsystem_devtype(monitor, "usb", "usb_device");
    udev_monitor_enable_receiving(monitor);
    udevFd = udev_monitor_get_fd(monitor);
}

void MonitorClient::cleanupUsbMonitoring()
{
    if (monitor) {
        udev_monitor_unref(monitor);
        monitor = nullptr;
    }
    if (udev) {
        udev_unref(udev);
        udev = nullptr;
    }
}

QString MonitorClient::getDeviceInfo(struct udev_device* dev)
{
    if (!dev) return QString();

    const char* vendor = udev_device_get_sysattr_value(dev, "idVendor");
    const char* product_name = udev_device_get_sysattr_value(dev, "product");

    return QString("%1 (VID: %2)")
        .arg(product_name ? product_name : "unknown")
        .arg(vendor ? vendor : "unknown");
}

void MonitorClient::processUsbEvent(struct udev_device* dev)
{
    if (!dev) return;

    const char* action = udev_device_get_action(dev);
    if (!action) return;

    const char* syspath = udev_device_get_syspath(dev);
    if (!syspath) return;

    QString deviceInfo;
    QString timestamp = QDateTime::currentDateTime().toTimeZone(QTimeZone("Asia/Vladivostok")).toString("yyyy-MM-dd HH:mm:ss");

    if (strcmp(action, "add") == 0) {
        deviceInfo = getDeviceInfo(dev);
        usbDeviceInfo[syspath] = deviceInfo;  // Store device info
        QJsonObject entryObj;
        entryObj["date"] = timestamp;
        entryObj["device_name"] = "Connected: " + deviceInfo;
        entryObj["action"] = "Nothing";  // Indicate this is a connection event

        usbLogs.append(entryObj);
        emit usbEvent();
    } else if (strcmp(action, "remove") == 0) {
        deviceInfo = usbDeviceInfo.value(syspath, "Unknown Device");  // Get stored info
        usbDeviceInfo.remove(syspath);  // Remove from map
        QJsonObject entryObj;
        entryObj["date"] = timestamp;
        entryObj["device_name"] = "Disconnected: " + deviceInfo;
        entryObj["action"] = "Nothing";  // Indicate this is a connection event

        usbLogs.append(entryObj);
        emit usbEvent();
    }
}

void MonitorClient::handleUsbEvent()
{
    if (!monitor) return;

    struct udev_device* dev = udev_monitor_receive_device(monitor);
    if (dev) {
        processUsbEvent(dev);
        udev_device_unref(dev);
    }
}

void MonitorClient::handleKeyPress(QString timestamp, const QString &windowTitle, const QString &keyText)
{
    QJsonObject entryObj;
    entryObj["date"] = timestamp;
    entryObj["application"] = windowTitle;
    entryObj["key"] = keyText;
    keyLogs.append(entryObj);

    if (monitorClientKeyLogVerbose()) {
        qDebug() << "[KeyLog] queued" << keyLogs.size() << "pending app:" << windowTitle;
    } else {
        const int n = keyLogs.size();
        if (n == 1)
            qInfo() << "[KeyLog] buffer: first keystroke queued";
        else if (n % 100 == 0)
            qInfo() << "[KeyLog] buffer:" << n << "keystrokes pending upload";
    }
}

int MonitorClient::getScreen()
{
    if (screenshotInProgress) {
        qDebug() << "Screenshot already in progress, skipping";
        return 0;
    }

    screenshotInProgress = true;
    const QString filePath = QStringLiteral("/tmp/tmp");

    setTrayActivity(QStringLiteral("Capturing…"));
    bool res = false;
    grabScreen(filePath, res);
    if (!res) {
        clearTrayActivity();
        screenshotInProgress = false;
        updateStatusDisplay();
        return 0;
    }

    setTrayActivity(QStringLiteral("Uploading screenshot…"));
    sendScreensAsync(filePath + QStringLiteral(".jpg"), [this, filePath](int sendResult) {
        screenshotInProgress = false;
        clearTrayActivity();
        if (sendResult == 0)
            QFile::remove(filePath + QStringLiteral(".jpg"));
        updateStatusDisplay();
        if (trayIcon && sendResult == 0)
            notifyTray(QStringLiteral("Screenshot"),
                                  QStringLiteral("Uploaded successfully."),
                                  QSystemTrayIcon::Information, 2500);
        else if (trayIcon && sendResult != 0)
            notifyTray(QStringLiteral("Screenshot"),
                                  QStringLiteral("Upload failed."),
                                  QSystemTrayIcon::Warning, 4000);
    });

    return 0;
}

void MonitorClient::grabEntireDesktop(QString path, QPixmap& res, bool& ok)
{
    QDBusConnection connection = QDBusConnection::sessionBus();
    connection.registerService("org.gnome.Screenshot");
    QDBusInterface gnomeInterface(
        QStringLiteral("org.gnome.Shell"),
        QStringLiteral("/org/gnome/Shell/Screenshot"),
        QStringLiteral("org.gnome.Shell.Screenshot"),
        connection);
    QDBusReply<bool> reply = gnomeInterface.call(
        QStringLiteral("Screenshot"), false, false, path);
    if (reply.value()) {
        res = QPixmap(path);
        QFile dbusResult(path);
        dbusResult.remove();
    } else {
        ok = false;
    }
    connection.unregisterService("org.gnome.Screenshot");
}

void MonitorClient::grabScreen(QString filePath, bool& ok)
{
    ok = false;

    // Try PipeWire portal capture first (Wayland with one-time approval)
    if (screenCastPortal && screenCastPortal->isStreaming()) {
        QImage frame = screenCastPortal->captureFrame();
        if (!frame.isNull()) {
            if (frame.width() > 1920 || frame.height() > 1080)
                frame = frame.scaled(1920, 1080, Qt::KeepAspectRatio,
                                     Qt::SmoothTransformation);
            ok = frame.save(filePath + ".jpg", "JPEG", 70);
            if (ok) {
                QFileInfo fi(filePath + ".jpg");
                qDebug() << "Screenshot (PipeWire):" << fi.size() / 1024 << "KB";
            }
            return;
        }
    }

    // Fallback: GNOME Shell D-Bus screenshot
    QPixmap p;
    bool desktopOk = true;
    grabEntireDesktop(filePath + ".png", p, desktopOk);
    if (desktopOk) {
        QImage image = p.toImage();
        if (image.width() > 1920 || image.height() > 1080)
            image = image.scaled(1920, 1080, Qt::KeepAspectRatio,
                                 Qt::SmoothTransformation);
        ok = image.save(filePath + ".jpg", "JPEG", 70);
        if (ok) {
            QFileInfo fi(filePath + ".jpg");
            qDebug() << "Screenshot (GNOME D-Bus):" << fi.size() / 1024 << "KB";
        }
    }
}

void MonitorClient::sendScreensAsync(const QString &filePath, std::function<void(int)> onDone)
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();

    if (serverIP.isEmpty()) {
        onDone(-3);
        return;
    }

    QNetworkRequest req(QUrl(QString("%1://%2:%3/webapi.php?type=upload").arg(serverPort == 8924 ? "http" : "https")
                                 .arg(serverIP)
                                 .arg(serverPort)));
    auto *multiPart = new QHttpMultiPart(QHttpMultiPart::FormDataType);

    QHttpPart comNamePart;
    comNamePart.setHeader(QNetworkRequest::ContentDispositionHeader,
                          QVariant(QStringLiteral("form-data; name=\"com_name\"")));
    comNamePart.setBody(clientName.toUtf8());
    multiPart->append(comNamePart);
    QHttpPart monitorPart;
    monitorPart.setHeader(QNetworkRequest::ContentDispositionHeader,
        QVariant(QStringLiteral("form-data; name=\"monitor_id\"")));
    monitorPart.setBody(QByteArray::number(monitorId));
    multiPart->append(monitorPart);

    QHttpPart ctimePart;
    ctimePart.setHeader(QNetworkRequest::ContentDispositionHeader,
                        QVariant(QStringLiteral("form-data; name=\"ctime\"")));
    ctimePart.setBody(QDateTime::currentDateTime().toString(QStringLiteral("yyyy-MM-dd HH:mm:ss")).toUtf8());
    multiPart->append(ctimePart);

    QHttpPart imagePart;
    imagePart.setHeader(QNetworkRequest::ContentTypeHeader, QVariant(QStringLiteral("image/jpeg")));
    imagePart.setHeader(QNetworkRequest::ContentDispositionHeader,
                        QVariant(QStringLiteral("form-data; name=\"imagefile\"; filename=\"screenshot.jpg\"")));
    imagePart.setRawHeader("Content-Transfer-Encoding", "binary");
    auto *file = new QFile(filePath);
    if (!file->open(QIODevice::ReadOnly)) {
        delete multiPart;
        onDone(-3);
        return;
    }
    imagePart.setBodyDevice(file);
    file->setParent(multiPart);
    multiPart->append(imagePart);

    if(authToken.isEmpty()||monitorId<1){delete multiPart;onDone(-3);return;}
    req.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply = networkManager->post(req, multiPart);
    multiPart->setParent(reply);

    connect(reply, &QNetworkReply::finished, this, [this, reply, onDone]() {
        int out = -3;
        if (reply->error() == QNetworkReply::NoError) {
            int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            if (statusCode == 200) {
                lastScreenshotSentTime = QDateTime::currentDateTime();
                out = 0;
            } else {
                qDebug() << "Screenshot upload HTTP status:" << statusCode;
                out = -1;
            }
        } else {
            qDebug() << "Screenshot upload failed:" << reply->errorString();
        }
        reply->deleteLater();
        onDone(out);
    });
}
int MonitorClient::sendTic()
{
    if(authToken.isEmpty()||monitorId<1)return 0;
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (serverIP.isEmpty())
        return -3;

    QJsonObject pingData;
    pingData["Event"] = "Tic";
    pingData["Version"] = MONITORAPP_VERSION;
    pingData["MacAddress"] = macAddress;
    pingData["MonitorId"] = monitorId;
    pingData["Username"] = clientName;

    QJsonDocument doc(pingData);
    QByteArray data = doc.toJson();

    QNetworkRequest request(QUrl(QString("%1://%2:%3/eventhandler.php").arg(serverPort == 8924 ? "http" : "https").arg(serverIP).arg(serverPort)));
    request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");

    request.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply = networkManager->post(request, data);
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        if (reply->error() == QNetworkReply::NoError) {
            int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            QByteArray body = reply->readAll();
            if (statusCode == 200) {
                serverConnected = true;
                lastError.clear();
                connectionErrorNotified = false;
                lastTicSentTime = QDateTime::currentDateTime();
                QJsonParseError jsonError;
                QJsonDocument jsonDoc = QJsonDocument::fromJson(body, &jsonError);
                if (jsonError.error == QJsonParseError::NoError && jsonDoc.isObject()) {
                    QJsonObject obj = jsonDoc.object();
                    if (obj.contains("LastBrowserTic")) {
                        bool wasUnset = (lastBrowserTic == -1);
                        lastBrowserTic = obj["LastBrowserTic"].toVariant().toLongLong();
                        if (wasUnset) {
                            m_lastEmitHistoryTime = 0;
                            qDebug() << "LastBrowserTic received:" << lastBrowserTic
                                     << "— browser history will fire on next interval";
                        }
                    }
                }
            } else {
                serverConnected = false;
                lastError = QStringLiteral("HTTP %1").arg(statusCode);
                showConnectionError(lastError);
            }
        } else {
            serverConnected = false;
            lastError = reply->errorString();
            showConnectionError(lastError);
        }
        updateStatusDisplay();
        reply->deleteLater();
    });

    return 0;
}

void MonitorClient::queryBrowserHistory(const QString& dbPath, const QString& query, const QString& browserName) {
    if (!QFile::exists(dbPath)) {
        return;
    }
    
    // Copy the database file since it might be locked by the browser
    QString tempDbPath = QDir::temp().filePath(QUuid::createUuid().toString() + ".db");
    if (!QFile::copy(dbPath, tempDbPath)) {
        qDebug() << "Failed to copy browser database";
        return;
    }
    
    {
        QSqlDatabase db = QSqlDatabase::addDatabase("QSQLITE", "browser_history");
        db.setDatabaseName(tempDbPath);
        
        if (db.open()) {
            QSqlQuery sqlQuery(db);
            
            // Get the last check time from server
            qint64 lastCheck = getLastCheckedTime(browserName);
            QString fieldNameForVisitTime = getFieldNameForVisitTime(browserName);

            // Build the query with correct WHERE/AND logic
            QString fullQuery = query.trimmed();
            QString condition = QString("%1 > %2").arg(fieldNameForVisitTime).arg(lastCheck);

            // Remove trailing ORDER BY if present
            QRegExp orderByRegex("\\bORDER\\s+BY\\b.*$", Qt::CaseInsensitive);
            QString orderByClause;
            int orderByPos = orderByRegex.indexIn(fullQuery);
            if (orderByPos != -1) {
                orderByClause = fullQuery.mid(orderByPos);
                fullQuery = fullQuery.left(orderByPos).trimmed();
            }

            // Add WHERE/AND condition
            if (fullQuery.contains(QRegExp("\\bWHERE\\b", Qt::CaseInsensitive))) {
                fullQuery += " AND " + condition;
            } else {
                fullQuery += " WHERE " + condition;
            }

            // Re-append ORDER BY if it was present
            if (!orderByClause.isEmpty()) {
                fullQuery += " " + orderByClause;
            } else {
                // Only add ORDER BY if not already present
                if (!fullQuery.contains(QRegExp("\\bORDER\\s+BY\\b", Qt::CaseInsensitive))) {
                    fullQuery += QString(" ORDER BY %1 ASC").arg(fieldNameForVisitTime);
                }
            }

            if (sqlQuery.exec(fullQuery)) {
                while (sqlQuery.next()) {
                    // Get visit_time as qint64 (may be microseconds or milliseconds depending on browser)
                    qint64 visitTime = sqlQuery.record().value("visit_time").toLongLong();
                    QDateTime dt;

                    // Convert visitTime to real time depending on browser
                    if (browserName == "Firefox" || browserName == "Midori") {
                        // Firefox/Midori: visit_time is in microseconds since epoch
                        dt = QDateTime::fromMSecsSinceEpoch(visitTime / 1000);
                    } else if (browserName == "Falkon") {
                        // Falkon: visit_time is in milliseconds since epoch
                        dt = QDateTime::fromMSecsSinceEpoch(visitTime);
                    } else {
                        // Chromium-based: visit_time is microseconds since 1601-01-01
                        // Convert to Unix epoch
                        qint64 unixTime = (visitTime / 1000000) - 11644473600LL;
                        dt = QDateTime::fromSecsSinceEpoch(unixTime);
                    }

                    QString url = sqlQuery.record().indexOf("url") != -1 ? sqlQuery.record().value("url").toString() : QString();
                    QString title = sqlQuery.record().indexOf("title") != -1 ? sqlQuery.record().value("title").toString() : QString();
                    QJsonObject historyItem;
                    historyItem["date"] = dt.toString("yyyy-MM-dd HH:mm:ss");
                    historyItem["browser"] = browserName;
                    historyItem["url"] = url;
                    if (!title.isEmpty()) {
                        historyItem["title"] = title;
                    }
                    browserHistories.append(historyItem);
                    if (lastCheck < visitTime) {
                        lastCheck = visitTime;
                    }
                }
                updateLastCheckedTime(browserName, lastCheck);
            } else {
                qDebug() << "Query failed:" << sqlQuery.lastError().text();
            }
            db.close();
        } else {
            qDebug() << "Failed to open database:" << db.lastError().text();
        }
    }
    
    QSqlDatabase::removeDatabase("browser_history");
    QFile::remove(tempDbPath);
    
}

QStringList MonitorClient::getMozBasedProfilePaths(const QString& browserPath) {
    QStringList profilePaths;
    
    // Traditional location
    QString firefoxPath = QDir::homePath() + browserPath;
    QDir dir(firefoxPath);
    QStringList filters;
    filters << "*.default-release" << "*.default-*" << "*.default" << "*.dev-edition-default" << "*.esr";
    QStringList profileDirs = dir.entryList(filters, QDir::Dirs);
    
    for (const QString& profileDir : profileDirs) {
        QString profilePath = firefoxPath + profileDir + "/places.sqlite";
        if (QFile::exists(profilePath)) {
            profilePaths.append(profilePath);
        }
    }

    // Check for additional profiles (Profile 1, Profile 2, etc.)
    QStringList additionalFilters;
    additionalFilters << "Profile*";
    QStringList additionalProfileDirs = dir.entryList(additionalFilters, QDir::Dirs);
    
    for (const QString& profileDir : additionalProfileDirs) {
        QString profilePath = firefoxPath + profileDir + "/places.sqlite";
        if (QFile::exists(profilePath)) {
            profilePaths.append(profilePath);
        }
    }

    QString snapPath;
    if (browserPath == "/.midori") {
        snapPath = QDir::homePath() + "/snap/midori/current/.config/midori/history.db";
        // Check Snap Midori location first
        if (QFile::exists(snapPath)) {
            profilePaths.append(snapPath);
        }
        // Fall back to traditional Midori location
        QString traditionalPath = QDir::homePath() + browserPath;
        if (QFile::exists(traditionalPath)) {
            profilePaths.append(traditionalPath);
        }
    } else {
        snapPath = QDir::homePath() + "/snap/firefox/common/.mozilla/firefox/";
        QDir snapDir(snapPath);
        QStringList snapFilters;
        snapFilters << "*.default" << "*.default-release" << "*.default-*" << "Profile*";
        QStringList snapProfileDirs = snapDir.entryList(snapFilters, QDir::Dirs);
        
        for (const QString& profileDir : snapProfileDirs) {
            QString profilePath = snapPath + profileDir + "/places.sqlite";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }

    return profilePaths;
}

QStringList MonitorClient::getChromiumBasedProfilePaths(const QString& browserPath) {
    QStringList profilePaths;
    QString path = QDir::homePath() + browserPath;
    QDir dir(path);
    
    if (dir.exists()) {
        // Get all profile directories
        QStringList profileDirs = dir.entryList(QDir::Dirs | QDir::NoDotAndDotDot);
        
        for (const QString& profileDir : profileDirs) {
            QString profilePath = path + "/" + profileDir + "/History";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }
    
    return profilePaths;
}

void MonitorClient::getFirefoxHistory() {
    QStringList profilePaths = getMozBasedProfilePaths("/.mozilla/firefox/");
    
    qDebug() << "Firefox history path:" << profilePaths;
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            // This query will extract url, title, and visit_time (microseconds since epoch)
            QString query =
                "SELECT p.url AS url, p.title AS title, h.visit_date AS visit_time "
                "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id";
            queryBrowserHistory(dbPath, query, "Firefox");
        }
    }
    
}

void MonitorClient::getChromeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/google-chrome/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Chrome");
        }
    }
    
}

void MonitorClient::getEdgeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/microsoft-edge/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Edge");
        }
    }
    
}

void MonitorClient::getOperaHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/opera/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Opera");
        }
    }
    
}

void MonitorClient::getBraveHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/BraveSoftware/Brave-Browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Brave");
        }
    }
    
}

void MonitorClient::getMidoriHistory() {
    QStringList profilePaths = getMozBasedProfilePaths("/.midori/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_date/1000, 'unixepoch', '+10:00') as last_visit_time, p.url, h.visit_date as visit_time "
                           "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id ";
            queryBrowserHistory(dbPath, query, "Midori");
        }
    }
    
}

void MonitorClient::getYandexHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/yandex-browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url as url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Yandex");
        }
    }
    
}

void MonitorClient::getSlimjetHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/slimjet/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url as url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Slimjet");
        }
    }
    
}

void MonitorClient::getFalkonHistory() {
    QStringList profilePaths;
    QString falkonBasePath = QDir::homePath() + "/.config/falkon/profiles/";
    QDir falkonDir(falkonBasePath);
    
    if (falkonDir.exists()) {
        QStringList profileDirs = falkonDir.entryList(QDir::Dirs | QDir::NoDotAndDotDot);
        for (const QString& profileDir : profileDirs) {
            QString profilePath = falkonBasePath + profileDir + "/browsedata.db";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(date/1000, 'unixepoch', '+10:00') as last_visit_time, url, date as visit_time "
                           "FROM history WHERE id > 0";
            queryBrowserHistory(dbPath, query, "Falkon");
        }
    }
    
}

void MonitorClient::getBrowserHistory() {
    // Get Firefox history
    getFirefoxHistory();
    
    // Get Chrome history
    getChromeHistory();
    
    // Get Edge history
    getEdgeHistory();
    
    // Get Opera history
    getOperaHistory();
    
    // Get Brave history
    getBraveHistory();
    
    // Get Falkon history
    getFalkonHistory();
    
    // Get Midori history
    getMidoriHistory();
    
    // Get Yandex history
    getYandexHistory();
    
    // Get Slimjet history
    getSlimjetHistory();
    
    // Get Vivaldi history
    getVivaldiHistory();
}

QString MonitorClient::getJsonObjectListAsJsonString(const QList<QJsonObject>& jsonObjectList) {
    QJsonArray jsonArray;
    for (const QJsonObject& obj : jsonObjectList) {
        jsonArray.append(obj);
    }
    
    QJsonDocument doc(jsonArray);
    return doc.toJson(QJsonDocument::Compact);
}

int MonitorClient::sendBrowserHistories()
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (serverIP.isEmpty() || serverPort <= 0)
        return -3;

    if (m_browserHistoryBusy.exchange(true))
        return 0;

    setTrayActivity(QStringLiteral("Collecting browser history…"));
    (void)QtConcurrent::run([this, serverIP, serverPort]() {
        getBrowserHistory();
        QList<QJsonObject> snapshot = browserHistories;
        browserHistories.clear();

        QMetaObject::invokeMethod(this, [this, serverIP, serverPort, snapshot]() {
            clearTrayActivity();
            if (snapshot.isEmpty()) {
                qDebug() << "No browser history to send";
                m_browserHistoryBusy = false;
                updateStatusDisplay();
                return;
            }
            QList<QList<QJsonObject>> chunks = chunkData(snapshot, 1000);
            qDebug() << "Sending" << chunks.size() << "browser history chunk(s)";
            setTrayActivity(QStringLiteral("Uploading browser history…"));
            continueSendBrowserChunks(0, chunks);
        }, Qt::QueuedConnection);
    });

    return 0;
}

void MonitorClient::getStorageDevices()
{
    struct udev* udev = udev_new();
    if (!udev) {
        return;
    }

    struct udev_enumerate* enumerate = udev_enumerate_new(udev);
    udev_enumerate_add_match_subsystem(enumerate, "usb");
    udev_enumerate_scan_devices(enumerate);

    struct udev_list_entry* devices_list = udev_enumerate_get_list_entry(enumerate);
    struct udev_list_entry* entry;
    udev_list_entry_foreach(entry, devices_list) {
        const char* path = udev_list_entry_get_name(entry);
        struct udev_device* dev = udev_device_new_from_syspath(udev, path);
        
        if (dev) {
            if (udev_device_get_devtype(dev) && strcmp(udev_device_get_devtype(dev), "usb_device") == 0) {
                const char* syspath = udev_device_get_syspath(dev);
                if (syspath) {
                    QString deviceInfo = getDeviceInfo(dev);
                    usbDeviceInfo[syspath] = deviceInfo;  // Store device info
                    
                    QString timestamp = QDateTime::currentDateTime().toTimeZone(QTimeZone("Asia/Vladivostok")).toString("yyyy-MM-dd HH:mm:ss");
                    QJsonObject entryObj;
                    entryObj["date"] = timestamp;
                    entryObj["device_name"] = "Connected: " + deviceInfo;
                    entryObj["action"] = "Nothing";  // Indicate this is a connection event
                    qDebug() << "Connected: " + deviceInfo;
                    usbLogs.append(entryObj);
                }
            }
            udev_device_unref(dev);
        }
    }
    
    udev_enumerate_unref(enumerate);
    udev_unref(udev);
}

void MonitorClient::monitorTimerTick()
{
    if(authToken.isEmpty()||monitorId<1)return;
    if (isInterruptionRequested() || checkActiveScreen() <= 0)
        return;

    const qint64 currentTime = QDateTime::currentSecsSinceEpoch();

    if (udevFd >= 0) {
        fd_set fds;
        struct timeval tv;
        FD_ZERO(&fds);
        FD_SET(udevFd, &fds);
        tv.tv_sec = 0;
        tv.tv_usec = 0;
        if (select(udevFd + 1, &fds, nullptr, nullptr, &tv) > 0)
            handleUsbEvent();
    }

    if (currentTime - m_lastEmitTicTime >= ticInterval) {
        emit tic();
        m_lastEmitTicTime = currentTime;
    }

    if (currentTime - m_lastEmitScreenshotTime >= screenInterval && screenshotEnabled) {
        emit screen();
        m_lastEmitScreenshotTime = currentTime;
    }

    if (currentTime - m_lastEmitHistoryTime >= historyInterval && browserHistoryEnabled) {
        if (lastBrowserTic != -1) {
            emit browserHistory();
            m_lastEmitHistoryTime = currentTime;
        }
    }

    if (currentTime - m_lastEmitKeyLogTime >= keyInterval && keylogEnabled) {
        if (monitorClientKeyLogVerbose())
            qDebug() << "[KeyLog] timer: emit keyLog signal, pending" << keyLogs.size();
        emit keyLog();
        m_lastEmitKeyLogTime = currentTime;
    }

    if (currentTime - m_lastEmitUsbLogTime >= usbInterval && usbMonitoringEnabled) {
        emit usbLog();
        m_lastEmitUsbLogTime = currentTime;
    }
}

qint64 MonitorClient::convertUnixtimestampToBrowserTime(const QString& browserName, const qint64 unixtimestamp) {
    if (browserName == "Firefox" || browserName == "Midori") {
        // Convert Unix timestamp (seconds) to microseconds
        return unixtimestamp * 1000000;
    } else if (browserName == "Chrome" || browserName == "Edge" || browserName == "Opera" || 
               browserName == "Brave" || browserName == "Yandex" || browserName == "Slimjet" || 
               browserName == "Vivaldi") {
        // Chromium-based browsers use microseconds since Jan 1, 1601
        // First convert Unix timestamp to microseconds, then add microseconds from 1601 to 1970
        const qint64 WINDOWS_TO_UNIX_EPOCH = 11644473600LL; // Seconds between 1601 and 1970
        return (unixtimestamp + WINDOWS_TO_UNIX_EPOCH) * 1000000;
    } else if (browserName == "Falkon") {
        return unixtimestamp * 1000;
    }
    return unixtimestamp; // Default case
}

qint64 MonitorClient::getLastCheckedTime(const QString& browserName) {
    if (!lastCheckedTimes.contains(browserName)) {
        lastCheckedTimes[browserName] = convertUnixtimestampToBrowserTime(browserName, lastBrowserTic);
    }
    return lastCheckedTimes.value(browserName);
}

void MonitorClient::updateLastCheckedTime(const QString& browserName, const qint64 timestamp) {
    lastCheckedTimes[browserName] = timestamp;
}

QString MonitorClient::getFieldNameForVisitTime(const QString& browserName) {
    if (browserName == "Firefox" || browserName == "Midori") {
        return "visit_date";
    } else if (browserName == "Chrome" || browserName == "Edge" || browserName == "Opera" || 
               browserName == "Brave" || browserName == "Yandex" || browserName == "Slimjet" || 
               browserName == "Vivaldi") {
        return "visit_time";
    }
    return "date";
}

int MonitorClient::sendKeyLogs()
{
    if (keyLogs.isEmpty()) {
        if (monitorClientKeyLogVerbose())
            qDebug() << "[KeyLog] sendKeyLogs: nothing to send";
        return 1;
    }
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (serverIP.isEmpty() || serverPort <= 0) {
        qWarning() << "[KeyLog] sendKeyLogs: invalid server IP/port in settings";
        return -3;
    }

    const int totalEvents = keyLogs.size();
    QList<QList<QJsonObject>> chunks = chunkData(keyLogs, 1000);
    keyLogs.clear();
    qInfo() << "[KeyLog] upload" << totalEvents << "events in" << chunks.size() << "chunk(s) ->"
            << serverIP << ":" << serverPort << "interval" << keyInterval << "s";
    continueSendKeyLogs(0, chunks);
    return 0;
}

int MonitorClient::sendUSBLogs()
{
    if (usbLogs.isEmpty()) {
        qDebug() << "No USB logs to send";
        return 1;
    }
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (serverIP.isEmpty() || serverPort <= 0)
        return -3;

    QList<QList<QJsonObject>> chunks = chunkData(usbLogs, 1000);
    usbLogs.clear();
    qDebug() << "Sending" << chunks.size() << "USB log chunk(s)";
    continueSendUsbLogs(0, chunks);
    return 0;
}

void MonitorClient::getVivaldiHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/vivaldi/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Vivaldi");
        }
    }
}

QList<QList<QJsonObject>> MonitorClient::chunkData(const QList<QJsonObject>& data, int chunkSize) {
    QList<QList<QJsonObject>> chunks;
    for (int i = 0; i < data.size(); i += chunkSize) {
        QList<QJsonObject> chunk;
        for (int j = i; j < i + chunkSize && j < data.size(); j++) {
            chunk.append(data[j]);
        }
        chunks.append(chunk);
    }
    return chunks;
}

void MonitorClient::sendDataChunkAsync(const QString &serverIP, int serverPort,
                                       const QString &eventType, const QString &dataField,
                                       const QString &data, std::function<void(int)> onDone)
{
    const QString url = QString("%1://%2:%3/eventhandler.php").arg(serverPort == 8924 ? "http" : "https").arg(serverIP).arg(serverPort);
    QNetworkRequest req{QUrl(url)};
    req.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json"));

    QJsonObject jsonObj;
    jsonObj["MacAddress"] = macAddress;
    jsonObj["MonitorId"] = monitorId;
    jsonObj["Event"] = eventType;
    jsonObj["Version"] = QString(MONITORAPP_VERSION);
    jsonObj["Username"] = clientName;

    QJsonParseError parseError;
    QJsonDocument dataDoc = QJsonDocument::fromJson(data.toUtf8(), &parseError);
    if (parseError.error == QJsonParseError::NoError && dataDoc.isArray())
        jsonObj[dataField] = dataDoc.array();
    else {
        qDebug() << "Failed to parse data as JSON array:" << parseError.errorString();
        jsonObj[dataField] = data;
    }

    if(authToken.isEmpty()||monitorId<1){onDone(-3);return;}
    req.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());
    QNetworkReply *reply = networkManager->post(req, QJsonDocument(jsonObj).toJson());
    connect(reply, &QNetworkReply::finished, this, [reply, onDone]() {
        int ret = -3;
        if (reply->error() == QNetworkReply::NoError) {
            int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            ret = (statusCode == 200) ? 1 : -1;
        } else {
            qDebug() << "sendDataChunkAsync:" << reply->errorString();
        }
        reply->deleteLater();
        onDone(ret);
    });
}

void MonitorClient::continueSendKeyLogs(int index, QList<QList<QJsonObject>> chunks)
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (index >= chunks.size()) {
        if (!chunks.isEmpty())
            qInfo() << "[KeyLog] upload batch finished" << chunks.size() << "chunk(s)";
        updateStatusDisplay();
        return;
    }
    QString chunkJson = getJsonObjectListAsJsonString(chunks[index]);
    sendDataChunkAsync(serverIP, serverPort, QStringLiteral("KeyLog"), QStringLiteral("KeyLogs"), chunkJson,
        [this, index, chunks](int ret) mutable {
            if (ret == 1) {
                qInfo() << "[KeyLog] chunk" << (index + 1) << "/" << chunks.size() << "HTTP OK,"
                        << chunks[index].size() << "events";
                lastKeySentTime = QDateTime::currentDateTime();
                updateStatusDisplay();
                QTimer::singleShot(100, this, [this, index, chunks]() {
                    continueSendKeyLogs(index + 1, chunks);
                });
            } else {
                qWarning() << "[KeyLog] chunk" << (index + 1) << "/" << chunks.size()
                           << "HTTP failed (non-200 or network error)," << chunks[index].size()
                           << "events lost from this batch";
                updateStatusDisplay();
            }
        });
}

void MonitorClient::continueSendUsbLogs(int index, QList<QList<QJsonObject>> chunks)
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (index >= chunks.size()) {
        updateStatusDisplay();
        return;
    }
    QString chunkJson = getJsonObjectListAsJsonString(chunks[index]);
    sendDataChunkAsync(serverIP, serverPort, QStringLiteral("USBLog"), QStringLiteral("USBLogs"), chunkJson,
        [this, index, chunks](int ret) mutable {
            if (ret == 1) {
                lastUsbSentTime = QDateTime::currentDateTime();
                updateStatusDisplay();
                QTimer::singleShot(100, this, [this, index, chunks]() {
                    continueSendUsbLogs(index + 1, chunks);
                });
            } else {
                qDebug() << "USB log chunk failed at" << index;
                updateStatusDisplay();
            }
        });
}

void MonitorClient::continueSendBrowserChunks(int index, QList<QList<QJsonObject>> chunks)
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (index >= chunks.size()) {
        clearTrayActivity();
        m_browserHistoryBusy = false;
        updateStatusDisplay();
        return;
    }
    setTrayActivity(QStringLiteral("Uploading history %1/%2…")
                        .arg(index + 1)
                        .arg(chunks.size()));
    QString chunkJson = getJsonObjectListAsJsonString(chunks[index]);
    sendDataChunkAsync(serverIP, serverPort, QStringLiteral("BrowserHistory"),
                        QStringLiteral("BrowserHistories"), chunkJson,
        [this, index, chunks](int ret) mutable {
            if (ret == 1) {
                lastBrowserSentTime = QDateTime::currentDateTime();
                updateStatusDisplay();
                QTimer::singleShot(100, this, [this, index, chunks]() {
                    continueSendBrowserChunks(index + 1, chunks);
                });
            } else {
                qDebug() << "Browser history chunk failed at" << index;
                clearTrayActivity();
                m_browserHistoryBusy = false;
                updateStatusDisplay();
            }
        });
}

bool MonitorClient::isInterruptionRequested()
{
    // Stub implementation - always return false for now
    return false;
}

bool MonitorClient::testAPIEndpoints(const QString& serverIP, int serverPort)
{
    qDebug() << "=== Testing API Endpoints ===";
    
    QStringList endpoints = {"/eventhandler.php", "/webapi.php"};
    
    for (const QString& endpoint : endpoints) {
        QString url = QString("%1://%2:%3%4").arg(serverPort == 8924 ? "http" : "https").arg(serverIP).arg(serverPort).arg(endpoint);
        qDebug() << "Testing endpoint:" << url;
        
        QNetworkRequest request(url);
        request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
        
        QJsonObject testData;
        testData["Event"] = "Tic";
        testData["Version"] = MONITORAPP_VERSION;
        testData["MacAddress"] = macAddress;
    testData["MonitorId"] = monitorId;
        testData["Username"] = clientName;
        
        QJsonDocument doc(testData);
        QByteArray data = doc.toJson();

        QNetworkAccessManager mgr;
        QEventLoop loop;
        QNetworkReply *reply = mgr.post(request, data);
        connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
        loop.exec();

        int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        QString response = reply->readAll();

        qDebug() << "  Endpoint:" << endpoint;
        qDebug() << "  Status:" << statusCode;
        qDebug() << "  Response:" << response;
        qDebug() << "  Error:" << reply->errorString();

        reply->deleteLater();
        
        if (reply->error() == QNetworkReply::NoError && statusCode == 200) {
            qDebug() << "  ✓ Endpoint" << endpoint << "is working";
            return true;
        } else {
            qDebug() << "  ✗ Endpoint" << endpoint << "failed";
        }
    }
    
    return false;
}

int MonitorClient::checkActiveScreen()
{
    // Stub implementation - return 1 to indicate screen is active
    return 1;
}

void MonitorClient::notifyTray(const QString &title,const QString &message,
                               QSystemTrayIcon::MessageIcon icon,int timeout)
{
    QSettings settings(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)
                       +"/monitorclient/settings.ini",QSettings::IniFormat);
    if(trayIcon && settings.value("Notifications/enabled",true).toBool())
        trayIcon->showMessage(title,message,icon,timeout);
}
// System tray implementation
void MonitorClient::setupSystemTray()
{
    if (trayIcon) {
        qWarning() << "setupSystemTray called twice; cleaning up previous tray";
        cleanupSystemTray();
    }

    // Create system tray icon
    trayIcon = new QSystemTrayIcon(this);
    trayIcon->setIcon(QIcon::fromTheme("monitor", QIcon::fromTheme("computer")));
    trayIcon->setToolTip("MonitorClient - System Monitoring");
    
    // Create tray menu (high-contrast status block: disabled QActions are dimmed by the theme)
    trayMenu = new QMenu();
    connect(trayMenu, &QMenu::aboutToShow, this, [this]() { updateTrayMenu(); });
    trayMenu->setStyleSheet(QStringLiteral(
        "QMenu { background-color: #ffffff; padding: 6px 4px; border: 1px solid #b8c0cc; }"
        "QMenu::item { padding: 8px 14px; color: #141820; }"
        "QMenu::item:selected { background-color: #dbe6ff; color: #0d1117; }"
        "QMenu::item:disabled { color: #0d1117; background-color: #e8ecf4; }"
        "QMenu::separator { height: 1px; background: #c5cdd8; margin: 6px 10px; }"));

    screenshotAction = new QAction("Take Screenshot", this);
    configureAction = new QAction("Configure...", this);
    testConnectionAction = new QAction("Test Connection", this);
    testAPIEndpointsAction = new QAction("Test API Endpoints", this);
    configRefreshAction = new QAction("Refresh Configuration", this);
    sendAllAction = new QAction("Send All Data", this);
    
    // Add actions to menu
    trayMenu->addAction(screenshotAction);
    trayMenu->addAction(configureAction);
    trayMenu->addAction(testConnectionAction);
    trayMenu->addAction(testAPIEndpointsAction);
    trayMenu->addAction(configRefreshAction);
    trayMenu->addSeparator();
    trayMenu->addAction(sendAllAction);
    trayMenu->addSeparator();
    trayMenu->addAction("Quit", qApp, &QApplication::quit);
    
    // Set menu for tray icon
    trayIcon->setContextMenu(trayMenu);
    
    // Connect signals
    connect(trayIcon, &QSystemTrayIcon::activated, this, &MonitorClient::onTrayIconActivated);
    connect(sendAllAction, &QAction::triggered, this, &MonitorClient::onSendAllAction);
    connect(configureAction, &QAction::triggered, this, &MonitorClient::onConfigureAction);
    connect(screenshotAction, &QAction::triggered, this, &MonitorClient::onTakeScreenshotAction);
    connect(testConnectionAction, &QAction::triggered, this, &MonitorClient::onTestConnectionAction);
    connect(testAPIEndpointsAction, &QAction::triggered, this, &MonitorClient::onTestAPIEndpointsAction);
    connect(configRefreshAction, &QAction::triggered, this, &MonitorClient::onConfigRefreshAction);
    
    // Show tray icon
    trayIcon->show();
    
    // Force initial update
    updateTrayMenu();
    
    qDebug() << "System tray setup completed";
}

void MonitorClient::cleanupSystemTray()
{
    if (trayIcon) {
        trayIcon->hide();
        delete trayIcon;
        trayIcon = nullptr;
    }
    
    if (trayMenu) {
        trayStatusDynamicActions.clear();
        delete trayMenu;
        trayMenu = nullptr;
    }
}

void MonitorClient::setTrayActivity(const QString &shortMessage)
{
    m_trayActivity = shortMessage;
    if (!m_trayActivity.isEmpty())
        qDebug() << "Tray activity:" << m_trayActivity;
    updateTrayIcon();
    updateTrayMenu();
}

void MonitorClient::clearTrayActivity()
{
    m_trayActivity.clear();
    updateTrayIcon();
    updateTrayMenu();
}

void MonitorClient::updateTrayIcon()
{
    if (!trayIcon)
        return;

    const bool active=serverConnected && !authToken.isEmpty() && monitorId>0;
    QPixmap icon(32,32);
    icon.fill(Qt::transparent);
    QPainter painter(&icon);
    painter.setRenderHint(QPainter::Antialiasing);
    painter.drawPixmap(2,2,QIcon::fromTheme("computer").pixmap(27,27));
    painter.setPen(QPen(Qt::white,2));
    painter.setBrush(active?QColor("#20bf55"):QColor("#e53935"));
    painter.drawEllipse(QRect(19,19,11,11));
    painter.end();
    trayIcon->setIcon(QIcon(icon));
    trayIcon->setToolTip(active?"MonitorClient v1.4 · Online":"MonitorClient v1.4 · Offline / Not signed in / No monitor");

}

void MonitorClient::updateTrayMenu()
{
    if (!trayMenu || !screenshotAction) {
        qDebug() << "Tray menu not properly initialized, reinitializing...";
        setupSystemTray();
        return;
    }

    for (QAction *a : trayStatusDynamicActions) {
        trayMenu->removeAction(a);
        delete a;
    }
    trayStatusDynamicActions.clear();

    const QString connLine = serverConnected
                                 ? QStringLiteral("Online")
                                 : QStringLiteral("Offline");
    const QString activityLine = m_trayActivity.isEmpty()
                                     ? QStringLiteral("Idle")
                                     : m_trayActivity;

    QStringList lines;
    lines << QStringLiteral("MonitorClient · %1").arg(connLine);
    lines << QStringLiteral("Server %1:%2")
                 .arg(getServerIP(), QString::number(getServerPort()));
    lines << QStringLiteral("Client: %1").arg(clientName);
    lines << QStringLiteral("Activity: %1").arg(activityLine);
    lines << QStringLiteral("Last screenshot: %1")
                 .arg(formatLastSentTime(lastScreenshotSentTime));
    lines << QStringLiteral("Last key log: %1")
                 .arg(formatLastSentTime(lastKeySentTime));
    lines << QStringLiteral("Last browser history: %1")
                 .arg(formatLastSentTime(lastBrowserSentTime));
    lines << QStringLiteral("Last USB log: %1")
                 .arg(formatLastSentTime(lastUsbSentTime));
    lines << QStringLiteral("Last heartbeat: %1").arg(formatLastSentTime(lastTicSentTime));
    lines << QStringLiteral("Features: screenshot %1 · key log %2 · history %3 · USB %4")
                 .arg(screenshotEnabled ? QStringLiteral("on") : QStringLiteral("off"),
                      keylogEnabled ? QStringLiteral("on") : QStringLiteral("off"),
                      browserHistoryEnabled ? QStringLiteral("on") : QStringLiteral("off"),
                      usbMonitoringEnabled ? QStringLiteral("on") : QStringLiteral("off"));

    if (!serverConnected && !lastError.isEmpty())
        lines << QStringLiteral("Error: %1").arg(lastError);

    // Insert so first string in `lines` is at top (native QActions — works in GNOME tray menus).
    for (int i = lines.size() - 1; i >= 0; --i) {
        QAction *row = new QAction(lines.at(i), trayMenu);
        row->setEnabled(false);
        trayMenu->insertAction(screenshotAction, row);
        trayStatusDynamicActions.append(row);
    }

    QAction *sep = new QAction(trayMenu);
    sep->setSeparator(true);
    trayMenu->insertAction(screenshotAction, sep);
    trayStatusDynamicActions.append(sep);
}

void MonitorClient::showConnectionError(const QString& error)
{
    lastError = error;
    serverConnected = false;
    
    // Update tray
    updateTrayIcon();
    updateTrayMenu();
    
    // Show notification only for first error
    QSettings local(QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)+"/monitorclient/settings.ini",QSettings::IniFormat);
    if (trayIcon && local.value("Notifications/enabled",true).toBool() && !connectionErrorNotified) {
        notifyTray("Connection Error", error, QSystemTrayIcon::Warning, 5000);
        connectionErrorNotified = true;
    }
    
    qDebug() << "Connection error:" << error;
}

QPair<bool, QString> MonitorClient::evaluateRemoteServer()
{
    const QString serverIP = getServerIP();
    const int serverPort = getServerPort();
    const QString url = QString("%1://%2:%3/eventhandler.php").arg(serverPort == 8924 ? "http" : "https").arg(serverIP).arg(serverPort);

    qDebug() << "evaluateRemoteServer:" << url;

    if (!testBasicConnectivity(serverIP, serverPort))
        return {false, QStringLiteral("Unreachable — check network/firewall")};

    if (!testAPIEndpoints(serverIP, serverPort))
        return {false, QStringLiteral("API endpoints not responding")};

    QJsonObject pingData;
    pingData["Event"] = "Tic";
    pingData["Version"] = MONITORAPP_VERSION;
    pingData["MacAddress"] = macAddress;
    pingData["MonitorId"] = monitorId;
    pingData["Username"] = clientName;

    QNetworkRequest request(url);
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json"));
    if(!authToken.isEmpty())request.setRawHeader("Authorization",("Bearer "+authToken).toUtf8());

    QNetworkAccessManager mgr;
    QNetworkReply *reply = mgr.post(request, QJsonDocument(pingData).toJson());
    QEventLoop loop;
    QObject::connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();

    const bool success = (reply->error() == QNetworkReply::NoError);
    const int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
    const QString errStr = reply->errorString();
    const QByteArray body = reply->readAll();
    reply->deleteLater();

    qDebug() << "Connection probe HTTP" << statusCode << "err" << errStr << "body" << body;

    if (success && statusCode == 200)
        return {true, QString()};
    if (statusCode != 200)
        return {false, QStringLiteral("HTTP %1 — %2").arg(statusCode).arg(errStr)};
    return {false, errStr};
}

void MonitorClient::scheduleConnectionTest(const QString &busyMessage,
                                           std::function<void(bool)> onFinished)
{
    const QString msg = busyMessage.isEmpty()
                            ? QStringLiteral("Testing connection…")
                            : busyMessage;
    setTrayActivity(msg);

    auto *watcher = new QFutureWatcher<QPair<bool, QString>>(this);
    QObject::connect(watcher, &QFutureWatcher<QPair<bool, QString>>::finished, this,
        [this, watcher, onFinished]() {
            const QPair<bool, QString> r = watcher->result();
            watcher->deleteLater();
            clearTrayActivity();
            if (r.first) {
                serverConnected = true;
                lastError.clear();
                connectionErrorNotified = false;
            } else {
                serverConnected = false;
                lastError = r.second;
                showConnectionError(lastError);
            }
            updateStatusDisplay();
            if (onFinished)
                onFinished(r.first);
        });
    watcher->setFuture(QtConcurrent::run([this]() { return evaluateRemoteServer(); }));
}

void MonitorClient::onTrayIconActivated(QSystemTrayIcon::ActivationReason reason)
{
    if (reason == QSystemTrayIcon::DoubleClick)
        scheduleConnectionTest(QStringLiteral("Testing connection…"),
            [this](bool ok) {
                if (!trayIcon)
                    return;
                if (ok)
                    notifyTray(QStringLiteral("Connection"),
                                          QStringLiteral("Server reachable."),
                                          QSystemTrayIcon::Information, 2500);
                else
                    notifyTray(QStringLiteral("Connection"),
                                          lastError,
                                          QSystemTrayIcon::Warning, 5000);
            });
}



void MonitorClient::onSendAllAction()
{
    qDebug() << "Send all data requested from system tray";
    setTrayActivity(QStringLiteral("Send all: collecting history…"));

    (void)QtConcurrent::run([this]() {
        getBrowserHistory();
        QList<QJsonObject> snap = browserHistories;
        browserHistories.clear();
        QMetaObject::invokeMethod(this, [this, snap]() mutable {
            browserHistories = std::move(snap);
            setTrayActivity(QStringLiteral("Send all: uploading…"));
            qDebug() << "Sending all data to server…";
            emit screen();
            emit tic();
            uploadPendingBrowserHistories();
            emit keyLog();
            emit usbLog();
            QTimer::singleShot(3500, this, [this]() {
                clearTrayActivity();
                updateStatusDisplay();
                if (trayIcon) {
                    notifyTray(QStringLiteral("Send all"),
                                          QStringLiteral("Operations queued or completed."),
                                          QSystemTrayIcon::Information, 2500);
                }
            });
        }, Qt::QueuedConnection);
    });
}

void MonitorClient::onConfigureAction()
{
    // The tray may be activated while a modal dialog's event loop is running.
    // Raise the existing window instead of constructing a second instance.
    if(activeSettingsDialog) {
        activeSettingsDialog->showNormal();
        activeSettingsDialog->raise();
        activeSettingsDialog->activateWindow();
        return;
    }
    auto *dialog=new ConfigDialog();
    activeSettingsDialog=dialog;
    connect(dialog,&ConfigDialog::sessionStateChanged,this,[this,dialog] {
        authToken=dialog->getAuthToken();
        monitorId=dialog->getMonitorId();
        if(authToken.isEmpty() || monitorId<1) {
            if(keyboardMonitor)keyboardMonitor->stopMonitoring();
        } else if(keyboardMonitor && !keyboardMonitor->startMonitoring()) {
            qWarning() << "[KeyLog] startMonitoring failed after pairing";
        }
        updateStatusDisplay();
    });
    connect(dialog,&QDialog::finished,this,[this,dialog](int result) {
        // Logout is applied on signal even when user dismisses the settings.
        if(result==QDialog::Accepted) {
            clientName=dialog->getClientName();
            authToken=dialog->getAuthToken();
            monitorId=dialog->getMonitorId();
            if(!authToken.isEmpty() && monitorId>0){
                if(keyboardMonitor && !keyboardMonitor->startMonitoring())
                    qWarning() << "[KeyLog] startMonitoring failed; check input permissions";
                scheduleConnectionTest(QStringLiteral("Verifying server…"), [this](bool ok) {
                    notifyTray("Configuration", ok ? "Saved and verified." :
                        "Saved; server check failed.",
                        ok ? QSystemTrayIcon::Information : QSystemTrayIcon::Warning,4000);
                });
            }
        }
        updateStatusDisplay();
        activeSettingsDialog.clear();
        dialog->deleteLater();
    });
    dialog->open(); // asynchronous modal; event loop never stacks settings dialogs
}

void MonitorClient::onTakeScreenshotAction()
{
    qDebug() << "Screenshot requested from system tray";
    emit screen();
}

void MonitorClient::updateStatusDisplay()
{
    updateTrayIcon();
    updateTrayMenu();
}

void MonitorClient::notifyRunningInstance()
{
    if (trayIcon) {
        notifyTray(QStringLiteral("MonitorClient"),
                              QStringLiteral("MonitorClient is already running in the system tray."),
                              QSystemTrayIcon::Information, 4000);
    }
}

void MonitorClient::enableScreenshot(bool enabled)
{
    screenshotEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::enableKeylog(bool enabled)
{
    keylogEnabled = enabled;
    qInfo() << "[KeyLog] uploads" << (enabled ? "enabled" : "disabled") << "interval" << keyInterval << "s";
    updateStatusDisplay();
}

void MonitorClient::enableBrowserHistory(bool enabled)
{
    browserHistoryEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::enableUsbMonitoring(bool enabled)
{
    usbMonitoringEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::onTestConnectionAction()
{
    qDebug() << "Manual connection test requested from system tray";
    scheduleConnectionTest(QStringLiteral("Testing connection…"), [this](bool ok) {
        if (!ok || !trayIcon)
            return;
        notifyTray(QStringLiteral("Connection test"),
                              QStringLiteral("Server reachable."),
                              QSystemTrayIcon::Information, 3000);
    });
}

void MonitorClient::onTestAPIEndpointsAction()
{
    qDebug() << "API endpoints test requested from system tray";
    const QString serverIP = getServerIP();
    const int serverPort = getServerPort();
    setTrayActivity(QStringLiteral("Testing API endpoints…"));

    auto *watcher = new QFutureWatcher<bool>(this);
    QObject::connect(watcher, &QFutureWatcher<bool>::finished, this, [this, watcher]() {
        const bool success = watcher->result();
        watcher->deleteLater();
        clearTrayActivity();
        if (trayIcon) {
            notifyTray(QStringLiteral("API test"),
                                  success ? QStringLiteral("Endpoints OK.")
                                          : QStringLiteral("Endpoints failed."),
                                  success ? QSystemTrayIcon::Information : QSystemTrayIcon::Warning,
                                  4000);
        }
        updateStatusDisplay();
    });
    watcher->setFuture(QtConcurrent::run([serverIP, serverPort, this]() {
        return testAPIEndpoints(serverIP, serverPort);
    }));
}

// Configuration Management Methods
void MonitorClient::fetchConfigurationFromAPI(bool showTrayProgress)
{
    if (isConfigUpdating) {
        qDebug() << "Configuration update already in progress, skipping";
        return;
    }
    
    isConfigUpdating = true;
    if (showTrayProgress)
        setTrayActivity(QStringLiteral("Refreshing configuration…"));
    qDebug() << "Fetching configuration from API";
    
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    QString configURL = QString("%1://%2:%3/webapi_config.php?platform=ubuntu").arg(serverPort == 8924 ? "http" : "https").arg(serverIP).arg(serverPort);
    
    QNetworkRequest request(configURL);
    request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    
    QNetworkReply *reply = networkManager->get(request);
    
    connect(reply, &QNetworkReply::finished, [this, reply, showTrayProgress]() {
        isConfigUpdating = false;
        if (showTrayProgress)
            clearTrayActivity();

        if (reply->error() == QNetworkReply::NoError) {
            QByteArray data = reply->readAll();
            QJsonParseError error;
            QJsonDocument doc = QJsonDocument::fromJson(data, &error);
            
            if (error.error == QJsonParseError::NoError) {
                QJsonObject root = doc.object();
                
                if (root["success"].toBool()) {
                    QJsonObject config = root["data"].toObject();
                    updateConfiguration(config);
                    qDebug() << "Configuration updated successfully";
                } else {
                    qDebug() << "Configuration fetch failed:" << root["error"].toString();
                }
            } else {
                qDebug() << "Configuration parsing failed:" << error.errorString();
            }
        } else {
            qDebug() << "Configuration fetch failed:" << reply->errorString();
        }

        updateStatusDisplay();
        reply->deleteLater();
    });
}

void MonitorClient::updateConfiguration(const QJsonObject& config)
{
    // Update intervals
    if (config.contains("intervals")) {
        QJsonObject intervals = config["intervals"].toObject();
        
        if (intervals.contains("screenshot")) {
            screenInterval = intervals["screenshot"].toInt();
        }
        if (intervals.contains("tic")) {
            ticInterval = intervals["tic"].toInt();
        }
        if (intervals.contains("history")) {
            historyInterval = intervals["history"].toInt();
        }
        if (intervals.contains("keylog")) {
            keyInterval = intervals["keylog"].toInt();
        }
        if (intervals.contains("usb")) {
            usbInterval = intervals["usb"].toInt();
        }
        if (intervals.contains("config_refresh")) {
            configRefreshInterval = intervals["config_refresh"].toInt();
        }
    }
    
    // Store current configuration
    currentConfig = config;
    lastConfigUpdate = QDateTime::currentDateTime();
    
    // Restart configuration timer with new interval
    configTimer->stop();
    configTimer->start(configRefreshInterval * 1000);
    
    // Log configuration update
    qDebug() << "Configuration updated:";
    qDebug() << "  Screenshot:" << screenInterval << "s";
    qDebug() << "  Tic:" << ticInterval << "s";
    qDebug() << "  History:" << historyInterval << "s";
    qDebug() << "  Keylog:" << keyInterval << "s";
    qDebug() << "  USB:" << usbInterval << "s";
    qDebug() << "  Config Refresh:" << configRefreshInterval << "s";
}

void MonitorClient::manualConfigRefresh()
{
    qDebug() << "Manual configuration refresh requested";
    fetchConfigurationFromAPI(true);
}

void MonitorClient::uploadPendingBrowserHistories()
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    if (serverIP.isEmpty() || serverPort <= 0 || browserHistories.isEmpty())
        return;
    if (m_browserHistoryBusy.exchange(true))
        return;
    QList<QList<QJsonObject>> chunks = chunkData(browserHistories, 1000);
    browserHistories.clear();
    if (chunks.isEmpty()) {
        m_browserHistoryBusy = false;
        return;
    }
    setTrayActivity(QStringLiteral("Uploading browser history…"));
    continueSendBrowserChunks(0, chunks);
}

void MonitorClient::onConfigRefreshAction()
{
    qDebug() << "Configuration refresh requested from system tray";
    manualConfigRefresh();
}

QString MonitorClient::formatLastSentTime(const QDateTime& time)
{
    if (!time.isValid()) {
        return "Never";
    }
    
    QDateTime now = QDateTime::currentDateTime();
    qint64 seconds = time.secsTo(now);
    
    if (seconds < 60) {
        return QString("%1s ago").arg(seconds);
    } else if (seconds < 3600) {
        return QString("%1m ago").arg(seconds / 60);
    } else if (seconds < 86400) {
        return QString("%1h ago").arg(seconds / 3600);
    } else {
        return time.toString("MM/dd HH:mm");
    }
}

QString MonitorClient::getLocalIPAddress()
{
    QList<QNetworkInterface> interfaces = QNetworkInterface::allInterfaces();
    
    for (const QNetworkInterface& interface : interfaces) {
        // Skip loopback and down interfaces
        if (interface.flags().testFlag(QNetworkInterface::IsLoopBack) ||
            !interface.flags().testFlag(QNetworkInterface::IsUp)) {
            continue;
        }
        
        QList<QNetworkAddressEntry> entries = interface.addressEntries();
        for (const QNetworkAddressEntry& entry : entries) {
            QHostAddress addr = entry.ip();
            if (addr.protocol() == QAbstractSocket::IPv4Protocol && 
                !addr.toString().startsWith("169.254")) { // Skip link-local addresses
                return addr.toString();
            }
        }
    }
    
    return "Unknown";
}

bool MonitorClient::testBasicConnectivity(const QString& host, int port)
{
    QTcpSocket socket;
    socket.connectToHost(host, port);
    
    if (socket.waitForConnected(5000)) {
        qDebug() << "Basic connectivity test successful to" << host << ":" << port;
        socket.disconnectFromHost();
        return true;
    } else {
        qDebug() << "Basic connectivity test failed to" << host << ":" << port;
        qDebug() << "Socket error:" << socket.errorString();
        return false;
    }
}
