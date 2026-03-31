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
    bool isStreaming() const { return m_streaming.load(); }

    void loadRestoreToken();
    void saveRestoreToken();

signals:
    void streamReady();
    void streamFailed(const QString &error);

private slots:
    void onCreateSessionResponse(const QDBusMessage &msg);
    void onSelectSourcesResponse(const QDBusMessage &msg);
    void onStartResponse(const QDBusMessage &msg);

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
