#ifndef SCREENCASTPORTAL_H
#define SCREENCASTPORTAL_H

#include <QObject>
#include <QImage>
#include <QMutex>
#include <QDBusMessage>
#include <atomic>

#include <pipewire/pipewire.h>
#include <spa/param/video/format-utils.h>

class ScreenCastPortal : public QObject
{
    Q_OBJECT

public:
    explicit ScreenCastPortal(QObject *parent = nullptr);
    ~ScreenCastPortal();

    bool start();
    void stop();
    QImage captureFrame();
    // Explicit, user-initiated fallback via the public desktop screenshot
    // portal; the compositor controls approval and delivers a file URI.
    bool requestScreenshot();
    bool isStreaming() const { return m_streaming.load(); }

    void loadRestoreToken();
    void saveRestoreToken();

signals:
    void streamReady();
    void streamFailed(const QString &error);
    void screenshotReady(const QImage &image);
    void screenshotFailed(const QString &error);

private slots:
    void onCreateSessionResponse(const QDBusMessage &msg);
    void onSelectSourcesResponse(const QDBusMessage &msg);
    void onStartResponse(const QDBusMessage &msg);
    void onScreenshotResponse(const QDBusMessage &msg);

private:
    void createSession();
    void selectSources();
    void startCapture();
    void openPipeWireRemote();

    void initPipeWire(int fd, uint32_t nodeId);
    void cleanupPipeWire();

    QString portalSenderName();
    QString nextRequestToken();
    QString requestObjectPath(const QString &token);
    uint32_t portalVersion();

    QString m_sessionHandle;
    QString m_restoreToken;
    QString m_screenshotRequestPath;
    bool m_screenshotRequestPending = false;
    uint32_t m_pipewireNode;
    std::atomic<bool> m_streaming;
    int m_tokenCounter;

    struct pw_thread_loop *m_pwLoop;
    struct pw_context *m_pwContext;
    struct pw_core *m_pwCore;
    struct pw_stream *m_pwStream;
    struct spa_hook m_streamListener;

    int32_t m_width;
    int32_t m_height;
    uint32_t m_spaFormat;

    QImage m_latestFrame;
    QMutex m_frameMutex;

    static void onPwStreamProcess(void *data);
    static void onPwStreamParamChanged(void *data, uint32_t id,
                                       const struct spa_pod *param);
    static void onPwStreamStateChanged(void *data, enum pw_stream_state old,
                                       enum pw_stream_state state,
                                       const char *error);
};

#endif // SCREENCASTPORTAL_H
