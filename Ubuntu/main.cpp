#include <QApplication>
#include <QDebug>
#include <QDir>
#include <QLockFile>
#include <QLocalServer>
#include <QLocalSocket>
#include <QMessageBox>
#include <QStandardPaths>
#include <QSysInfo>

#include <unistd.h>

#include "MonitorClient.h"

static QString instanceServerName()
{
    return QStringLiteral("MonitorClient_%1").arg(getuid());
}

int main(int argc, char *argv[])
{
    QApplication app(argc, argv);
    // This is a tray-resident client. Closing the last Settings dialog must
    // not stop monitoring, destroy the tray icon, or end the event loop.
    // The explicit Quit action, an approved update, or shutdown can still exit.
    app.setQuitOnLastWindowClosed(false);
    QObject::connect(&app, &QCoreApplication::aboutToQuit, []() {
        qInfo() << "[Lifecycle] Qt event loop exiting; background monitoring stopping";
    });
    qInfo() << "[Lifecycle] Tray mode initialized: closing Settings will not quit";

    const QString cacheDir = QStandardPaths::writableLocation(QStandardPaths::CacheLocation);
    QDir().mkpath(cacheDir);
    const QString lockPath = cacheDir + QStringLiteral("/monitorclient.lock");

    QLockFile instanceLock(lockPath);
    instanceLock.setStaleLockTime(30000);
    if (!instanceLock.tryLock(100)) {
        QLocalSocket probe;
        probe.connectToServer(instanceServerName());
        if (probe.waitForConnected(1000)) {
            probe.write("RAISE");
            probe.waitForBytesWritten(500);
            probe.disconnectFromServer();
        } else {
            QMessageBox::warning(
                nullptr,
                QStringLiteral("MonitorClient"),
                QStringLiteral("Only one instance may run.\n\n"
                               "If you closed MonitorClient recently, wait up to 30 seconds, or remove:\n%1")
                    .arg(lockPath));
        }
        return 0;
    }

    app.setStyle(QStringLiteral("Fusion"));
    app.setStyleSheet(QStringLiteral(
        "QWidget { font-family: 'Noto Sans', 'Segoe UI', 'Liberation Sans', sans-serif; font-size: 13px; }"
        "QDialog { background-color: #f4f6fa; }"
        "QGroupBox { font-weight: 600; border: 1px solid #c9d4e4; border-radius: 6px; "
        "margin-top: 12px; padding-top: 8px; background-color: #fafbfd; }"
        "QGroupBox::title { subcontrol-origin: margin; left: 10px; padding: 0 4px; color: #1a2b4a; }"
        "QLineEdit, QSpinBox { border: 1px solid #c5ced9; border-radius: 4px; padding: 7px; "
        "background: #ffffff; selection-background-color: #cfe0ff; }"
        "QSpinBox { padding: 6px; }"
        "QLabel { color: #2b3a50; }"
        "QPushButton { background: #2d6cdf; color: #ffffff; border: none; "
        "border-radius: 5px; padding: 8px 18px; min-width: 100px; font-weight: 500; }"
        "QPushButton:hover { background: #2458b8; }"
        "QPushButton:pressed { background: #1d4a9a; }"
        "QMessageBox QLabel { min-width: 360px; }"));

    qDebug() << "=== Ubuntu MonitorClient Starting ===";
    qDebug() << "Qt Version:" << qVersion();
    qDebug() << "System Architecture:" << QSysInfo::currentCpuArchitecture();

    MonitorClient client;

    const QString ipcName = instanceServerName();
    QLocalServer::removeServer(ipcName);
    QLocalServer ipcServer;
    if (!ipcServer.listen(ipcName))
        qWarning() << "IPC listen failed (second-instance raise disabled):" << ipcServer.errorString();

    QObject::connect(&ipcServer, &QLocalServer::newConnection, &client, [&ipcServer, &client]() {
        QLocalSocket *sock = ipcServer.nextPendingConnection();
        if (!sock)
            return;
        QObject::connect(sock, &QLocalSocket::disconnected, sock, &QLocalSocket::deleteLater);
        sock->readAll();
        client.notifyRunningInstance();
    });

    // MonitorClient already connects all timed upload signals in its
    // constructor. Do not connect them again here: duplicate slots would
    // attempt two screenshots/uploads for every timer tick.
    QObject::connect(&client, &MonitorClient::usbEvent, &client, &MonitorClient::handleUsbEvent);

    qDebug() << "MonitorClient started successfully (~single instance).";

    return app.exec();
}
