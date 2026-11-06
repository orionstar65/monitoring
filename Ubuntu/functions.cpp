#include "functions.h"
#include <QSettings>
#include <QFile>
#include <QDebug>
#include <QStandardPaths>

namespace {

// The dialog writes to the per-user config directory. All background
// uploaders must read that same file, not an unrelated settings.ini from the
// process working directory (which might be /opt, /, or a systemd directory).
QString canonicalSettingsPath()
{
    return QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)
           + QStringLiteral("/monitorclient/settings.ini");
}

QString activeSettingsPath(const QString &legacyPath = QStringLiteral("settings.ini"))
{
    const QString canonical = canonicalSettingsPath();
    // Older releases used a working-directory INI. Read it only when the
    // canonical settings file has not been created yet.
    return QFile::exists(canonical) ? canonical : legacyPath;
}

QVariant currentSetting(const QString &key, const QVariant &defaultValue = QVariant())
{
    const QString path = activeSettingsPath();
    if (!QFile::exists(path))
        return defaultValue;
    // QSettings reads the current on-disk values; never cache the server
    // address at startup, since Settings can be saved while the app runs.
    QSettings settings(path, QSettings::IniFormat);
    settings.sync();
    return settings.value(key, defaultValue);
}

} // namespace

QMap<QString, QVariant> loadSettings(const QString &filePath)
{
    QMap<QString, QVariant> values;
    const QString path = activeSettingsPath(filePath);
    if (!QFile::exists(path)) {
        qWarning() << "[Settings] No saved server configuration found at" << path;
        return values;
    }

    QSettings settings(path, QSettings::IniFormat);
    settings.sync();
    for (const QString &key : settings.allKeys())
        values.insert(key, settings.value(key));
    qInfo() << "[Settings] Loaded server configuration from" << path;
    return values;
}

QString getUsername()
{
    const QString saved = currentSetting(QStringLiteral("Server/username")).toString().trimmed();
    return saved.isEmpty() ? QString::fromLocal8Bit(qgetenv("USER")) : saved;
}

QString getClientName()
{
    const QString saved = currentSetting(QStringLiteral("Client/name")).toString().trimmed();
    return saved.isEmpty() ? getUsername() : saved;
}

QString getServerIP()
{
    // Do not silently upload to localhost when a saved server address is
    // missing. Callers can report the actual configuration problem.
    return currentSetting(QStringLiteral("Server/ip")).toString().trimmed();
}

int getServerPort()
{
    const int port = currentSetting(QStringLiteral("Server/port"), 8924).toInt();
    return port >= 1 && port <= 65535 ? port : 0;
}
