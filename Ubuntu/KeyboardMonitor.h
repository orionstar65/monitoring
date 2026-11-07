#ifndef KEYBOARDMONITOR_H
#define KEYBOARDMONITOR_H

#include <QObject>
#include <QProcess>
#include <QString>

class KeyboardMonitor : public QObject
{
    Q_OBJECT

public:
    explicit KeyboardMonitor(QObject *parent = nullptr);
    ~KeyboardMonitor();

    bool startMonitoring();
    void stopMonitoring();

signals:
    void keyPressed(QString timestamp, const QString &windowTitle, const QString &keyText);
    // Status metadata only: never expose or include captured key contents.
    void monitoringStatusChanged(bool running, const QString &description);

private slots:
    void onReadyRead();
    void onProcessFinished(int exitCode, QProcess::ExitStatus status);

private:
    QString helperPath() const;
    QString getActiveWindowTitle();
    QString findDesktopFile(const QString &appClass);

    QProcess *m_helper = nullptr;
    QByteArray m_buf;
};

#endif // KEYBOARDMONITOR_H
