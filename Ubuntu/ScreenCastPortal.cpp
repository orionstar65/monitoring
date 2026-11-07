#include "ScreenCastPortal.h"

#include <QDBusArgument>
#include <QDBusConnection>
#include <QDBusInterface>
#include <QDBusMessage>
#include <QDBusObjectPath>
#include <QDBusPendingReply>
#include <QDBusPendingCallWatcher>
#include <QFileInfo>
#include <QImageReader>
#include <QTimer>
#include <QUrl>
#include <QStandardPaths>
#include <QDir>
#include <QDBusUnixFileDescriptor>
#include <QDebug>
#include <QSettings>

#include <spa/param/props.h>
#include <spa/pod/builder.h>
#include <spa/param/video/format-utils.h>
#include <spa/utils/result.h>

#include <fcntl.h>
#include <unistd.h>

#define PORTAL_SERVICE       "org.freedesktop.portal.Desktop"
#define PORTAL_PATH          "/org/freedesktop/portal/desktop"
#define PORTAL_REQUEST_IF    "org.freedesktop.portal.Request"
#define PORTAL_SESSION_IF    "org.freedesktop.portal.Session"
#define PORTAL_SCREENCAST_IF "org.freedesktop.portal.ScreenCast"
#define PORTAL_SCREENSHOT_IF "org.freedesktop.portal.Screenshot"

#define CAPTURE_TYPE_MONITOR    1
#define PERSIST_MODE_PERSISTENT 2

static QImage::Format spaToQImageFormat(uint32_t format)
{
    switch (format) {
    case SPA_VIDEO_FORMAT_BGRx:  return QImage::Format_RGB32;
    case SPA_VIDEO_FORMAT_BGRA:  return QImage::Format_ARGB32;
    case SPA_VIDEO_FORMAT_RGBx:  return QImage::Format_RGBX8888;
    case SPA_VIDEO_FORMAT_RGBA:  return QImage::Format_RGBA8888;
    case SPA_VIDEO_FORMAT_RGB:   return QImage::Format_RGB888;
    default:                     return QImage::Format_Invalid;
    }
}

// --- PipeWire stream callbacks (static, run on PipeWire thread) ---

void ScreenCastPortal::onPwStreamStateChanged(void *data,
                                              enum pw_stream_state old,
                                              enum pw_stream_state state,
                                              const char *error)
{
    Q_UNUSED(old);
    auto *self = static_cast<ScreenCastPortal *>(data);

    qDebug() << "PipeWire stream state:"
             << pw_stream_state_as_string(old) << "->"
             << pw_stream_state_as_string(state);

    if (state == PW_STREAM_STATE_STREAMING) {
        self->m_streaming.store(true);
        QMetaObject::invokeMethod(self, "streamReady", Qt::QueuedConnection);
    } else if (state == PW_STREAM_STATE_ERROR) {
        self->m_streaming.store(false);
        QString err = error ? QString::fromUtf8(error) : "Unknown error";
        qWarning() << "PipeWire stream error:" << err;
        QMetaObject::invokeMethod(self,[self,err]() {
            emit self->streamFailed(QStringLiteral("PipeWire stream: ") + err);
        },Qt::QueuedConnection);
    } else if (state == PW_STREAM_STATE_UNCONNECTED) {
        self->m_streaming.store(false);
    }
}

void ScreenCastPortal::onPwStreamParamChanged(void *data, uint32_t id,
                                              const struct spa_pod *param)
{
    auto *self = static_cast<ScreenCastPortal *>(data);
    if (!param || id != SPA_PARAM_Format)
        return;

    struct spa_video_info info;
    spa_format_parse(param, &info.media_type, &info.media_subtype);
    if (info.media_type != SPA_MEDIA_TYPE_video ||
        info.media_subtype != SPA_MEDIA_SUBTYPE_raw)
        return;

    spa_format_video_raw_parse(param, &info.info.raw);

    self->m_width     = info.info.raw.size.width;
    self->m_height    = info.info.raw.size.height;
    self->m_spaFormat = info.info.raw.format;

    qDebug() << "PipeWire negotiated format:"
             << self->m_width << "x" << self->m_height
             << "spa_format=" << self->m_spaFormat;

    uint8_t buf[1024];
    struct spa_pod_builder b = SPA_POD_BUILDER_INIT(buf, sizeof(buf));
    const struct spa_pod *params[1];

    params[0] = (const struct spa_pod *)spa_pod_builder_add_object(&b,
        SPA_TYPE_OBJECT_ParamBuffers, SPA_PARAM_Buffers,
        SPA_PARAM_BUFFERS_buffers, SPA_POD_CHOICE_RANGE_Int(8, 1, 32),
        // Desktop screencast providers commonly negotiate MemFd buffers,
        // not just MemPtr. MAP_BUFFERS makes MemFd payloads readable.
        SPA_PARAM_BUFFERS_dataType, SPA_POD_Int(
            (1 << SPA_DATA_MemPtr) | (1 << SPA_DATA_MemFd)));

    pw_stream_update_params(self->m_pwStream, params, 1);
}

void ScreenCastPortal::onPwStreamProcess(void *data)
{
    auto *self = static_cast<ScreenCastPortal *>(data);

    struct pw_buffer *pwBuf = pw_stream_dequeue_buffer(self->m_pwStream);
    if (!pwBuf)
        return;

    struct spa_buffer *spaBuf = pwBuf->buffer;
    if (spaBuf->n_datas < 1 || !spaBuf->datas[0].data ||
        !spaBuf->datas[0].chunk) {
        pw_stream_queue_buffer(self->m_pwStream, pwBuf);
        return;
    }

    struct spa_data *d = &spaBuf->datas[0];
    QImage::Format qfmt = spaToQImageFormat(self->m_spaFormat);
    if (qfmt == QImage::Format_Invalid) {
        pw_stream_queue_buffer(self->m_pwStream, pwBuf);
        return;
    }

    int stride = d->chunk->stride;
    if (stride <= 0) {
        int bpp = (qfmt == QImage::Format_RGB888) ? 3 : 4;
        stride = self->m_width * bpp;
    }

    const int bytesPerPixel = qfmt==QImage::Format_RGB888?3:4;
    if(self->m_width<=0 || self->m_height<=0 ||
       stride<self->m_width*bytesPerPixel ||
       d->chunk->offset>d->maxsize ||
       quint64(stride)*quint64(self->m_height)>
           quint64(d->maxsize-d->chunk->offset)){
        pw_stream_queue_buffer(self->m_pwStream,pwBuf);
        return;
    }
    const uint8_t *pixels = static_cast<const uint8_t *>(d->data)
                            + d->chunk->offset;
    QImage frame(pixels, self->m_width, self->m_height, stride, qfmt);

    {
        QMutexLocker lock(&self->m_frameMutex);
        self->m_latestFrame = frame.copy();
    }

    pw_stream_queue_buffer(self->m_pwStream, pwBuf);
}

// --- Constructor / Destructor ---

ScreenCastPortal::ScreenCastPortal(QObject *parent)
    : QObject(parent)
    , m_pipewireNode(0)
    , m_streaming(false)
    , m_tokenCounter(0)
    , m_pwLoop(nullptr)
    , m_pwContext(nullptr)
    , m_pwCore(nullptr)
    , m_pwStream(nullptr)
    , m_streamListener{}
    , m_width(0)
    , m_height(0)
    , m_spaFormat(SPA_VIDEO_FORMAT_UNKNOWN)
{
    pw_init(nullptr, nullptr);
}

ScreenCastPortal::~ScreenCastPortal()
{
    stop();
}

// --- Public API ---

bool ScreenCastPortal::start()
{
    loadRestoreToken();
    createSession();
    return true;
}

void ScreenCastPortal::stop()
{
    if(m_screenshotRequestPending){
        QDBusConnection::sessionBus().disconnect(
            "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
            this,SLOT(onScreenshotResponse(QDBusMessage)));
        m_screenshotRequestPending=false;
        m_screenshotRequestPath.clear();
    }
    cleanupPipeWire();

    if (!m_sessionHandle.isEmpty()) {
        QDBusMessage closeMsg = QDBusMessage::createMethodCall(
            PORTAL_SERVICE, m_sessionHandle, PORTAL_SESSION_IF, "Close");
        QDBusConnection::sessionBus().call(closeMsg, QDBus::NoBlock);
        m_sessionHandle.clear();
    }

    m_streaming.store(false);
}

QImage ScreenCastPortal::captureFrame()
{
    QMutexLocker lock(&m_frameMutex);
    return m_latestFrame;
}

// Public screenshot portal fallback. It is only called from a real user
// screenshot action; GNOME controls consent, not the monitoring application.
bool ScreenCastPortal::requestScreenshot()
{
    if (m_screenshotRequestPending) return true;

    QDBusConnection bus=QDBusConnection::sessionBus();
    if(!bus.isConnected()){
        emit screenshotFailed(QStringLiteral("No desktop session D-Bus bus. Open the app from a logged-in desktop session (not sudo/system service)."));
        return false;
    }

    const QString token=nextRequestToken();
    const QString requestPath=requestObjectPath(token);
    if(!bus.connect("",requestPath,PORTAL_REQUEST_IF,"Response",
                    this,SLOT(onScreenshotResponse(QDBusMessage)))){
        emit screenshotFailed(QStringLiteral("Could not subscribe to desktop portal screenshot response"));
        return false;
    }

    m_screenshotRequestPending=true;
    m_screenshotRequestPath=requestPath;
    QDBusMessage call=QDBusMessage::createMethodCall(
        PORTAL_SERVICE,PORTAL_PATH,PORTAL_SCREENSHOT_IF,"Screenshot");
    QVariantMap options;
    options["handle_token"]=token;
    call << QString() << QVariant::fromValue(options);
    auto *watcher=new QDBusPendingCallWatcher(bus.asyncCall(call),this);
    connect(watcher,&QDBusPendingCallWatcher::finished,this,
        [this,requestPath](QDBusPendingCallWatcher *finished){
            QDBusPendingReply<QDBusObjectPath> reply=*finished;
            finished->deleteLater();
            if(m_screenshotRequestPath!=requestPath)return;
            if(reply.isError()){
                QDBusConnection::sessionBus().disconnect(
                    "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
                    this,SLOT(onScreenshotResponse(QDBusMessage)));
                m_screenshotRequestPending=false;
                m_screenshotRequestPath.clear();
                emit screenshotFailed(QStringLiteral("Desktop screenshot portal request failed: ")+reply.error().message());
                return;
            }
            // Some portal implementations return a different request path.
            // Listen on the actual returned path as well.
            const QString actual=reply.value().path();
            if(!actual.isEmpty() && actual!=m_screenshotRequestPath){
                QDBusConnection::sessionBus().disconnect(
                    "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
                    this,SLOT(onScreenshotResponse(QDBusMessage)));
                m_screenshotRequestPath=actual;
                QDBusConnection::sessionBus().connect(
                    "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
                    this,SLOT(onScreenshotResponse(QDBusMessage)));
            }
        });

    QTimer::singleShot(45000,this,[this,requestPath](){
        // A granted capture clears the request before this timer fires.
        if(!m_screenshotRequestPending)return;
        if(m_screenshotRequestPath!=requestPath)return;
        QDBusConnection::sessionBus().disconnect(
            "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
            this,SLOT(onScreenshotResponse(QDBusMessage)));
        m_screenshotRequestPending=false;
        m_screenshotRequestPath.clear();
        emit screenshotFailed(QStringLiteral("Desktop permission dialog timed out. Approve screenshot sharing and try again."));
    });
    return true;
}

void ScreenCastPortal::onScreenshotResponse(const QDBusMessage &msg)
{
    if(!m_screenshotRequestPending)return;
    QDBusConnection::sessionBus().disconnect(
        "",m_screenshotRequestPath,PORTAL_REQUEST_IF,"Response",
        this,SLOT(onScreenshotResponse(QDBusMessage)));
    m_screenshotRequestPending=false;
    m_screenshotRequestPath.clear();

    if(msg.arguments().size()<2){
        emit screenshotFailed(QStringLiteral("Desktop screenshot portal returned an incomplete response"));
        return;
    }
    const uint status=msg.arguments().at(0).toUInt();
    if(status!=0){
        emit screenshotFailed(status==1
            ? QStringLiteral("Screenshot permission was cancelled")
            : QStringLiteral("Desktop denied screenshot access"));
        return;
    }
    QVariantMap result;
    const QVariant value=msg.arguments().at(1);
    if(value.canConvert<QDBusArgument>()){
        QDBusArgument arg=value.value<QDBusArgument>();
        arg >> result;
    }else result=value.toMap();

    const QString uri=result.value(QStringLiteral("uri")).toString();
    const QString path=QUrl(uri).toLocalFile();
    if(path.isEmpty()){
        emit screenshotFailed(QStringLiteral("Desktop screenshot portal returned no readable file URI"));
        return;
    }
    QImageReader reader(path);
    const QImage captured=reader.read();
    if(captured.isNull()){
        emit screenshotFailed(QStringLiteral("Desktop screenshot file cannot be decoded: ")+reader.errorString());
        return;
    }
    emit screenshotReady(captured);
}

// --- Portal D-Bus flow: CreateSession -> SelectSources -> Start -> OpenPipeWireRemote ---

void ScreenCastPortal::createSession()
{
    QString reqToken = nextRequestToken();
    QString sessToken = QString("mc_session_%1").arg(m_tokenCounter);
    QString reqPath = requestObjectPath(reqToken);

    QDBusConnection bus = QDBusConnection::sessionBus();
    bus.connect("", reqPath, PORTAL_REQUEST_IF, "Response",
                this, SLOT(onCreateSessionResponse(QDBusMessage)));

    QDBusMessage msg = QDBusMessage::createMethodCall(
        PORTAL_SERVICE, PORTAL_PATH, PORTAL_SCREENCAST_IF, "CreateSession");

    QVariantMap options;
    options["handle_token"] = reqToken;
    options["session_handle_token"] = sessToken;
    msg << QVariant::fromValue(options);

    QDBusPendingCall pending = bus.asyncCall(msg);
    Q_UNUSED(pending);
}

void ScreenCastPortal::onCreateSessionResponse(const QDBusMessage &msg)
{
    QDBusConnection::sessionBus().disconnect(
        "", msg.path(), PORTAL_REQUEST_IF, "Response",
        this, SLOT(onCreateSessionResponse(QDBusMessage)));

    uint response = msg.arguments().at(0).toUInt();
    if (response != 0) {
        emit streamFailed(QString("CreateSession denied (code %1)").arg(response));
        return;
    }

    QDBusArgument resultsArg = msg.arguments().at(1).value<QDBusArgument>();
    QVariantMap results;
    resultsArg >> results;

    if (results.contains("session_handle")) {
        QVariant v = results["session_handle"];
        m_sessionHandle = v.canConvert<QDBusObjectPath>()
                              ? v.value<QDBusObjectPath>().path()
                              : v.toString();
    }

    if (m_sessionHandle.isEmpty()) {
        emit streamFailed("CreateSession returned empty session handle");
        return;
    }

    qDebug() << "Portal session created:" << m_sessionHandle;
    selectSources();
}

void ScreenCastPortal::selectSources()
{
    QString reqToken = nextRequestToken();
    QString reqPath = requestObjectPath(reqToken);

    QDBusConnection bus = QDBusConnection::sessionBus();
    bus.connect("", reqPath, PORTAL_REQUEST_IF, "Response",
                this, SLOT(onSelectSourcesResponse(QDBusMessage)));

    QDBusMessage msg = QDBusMessage::createMethodCall(
        PORTAL_SERVICE, PORTAL_PATH, PORTAL_SCREENCAST_IF, "SelectSources");

    QVariantMap options;
    options["handle_token"] = reqToken;
    options["types"] = QVariant::fromValue(static_cast<uint>(CAPTURE_TYPE_MONITOR));
    options["multiple"] = false;

    uint32_t version = portalVersion();
    if (version >= 4) {
        options["persist_mode"] = QVariant::fromValue(
            static_cast<uint>(PERSIST_MODE_PERSISTENT));
        if (!m_restoreToken.isEmpty()) {
            options["restore_token"] = m_restoreToken;
            qDebug() << "Using restore token for persistent permission";
        }
    }

    msg << QVariant::fromValue(QDBusObjectPath(m_sessionHandle));
    msg << QVariant::fromValue(options);

    QDBusPendingCall pending = bus.asyncCall(msg);
    Q_UNUSED(pending);
}

void ScreenCastPortal::onSelectSourcesResponse(const QDBusMessage &msg)
{
    QDBusConnection::sessionBus().disconnect(
        "", msg.path(), PORTAL_REQUEST_IF, "Response",
        this, SLOT(onSelectSourcesResponse(QDBusMessage)));

    uint response = msg.arguments().at(0).toUInt();
    if (response != 0) {
        emit streamFailed(QString("SelectSources denied (code %1)").arg(response));
        return;
    }

    qDebug() << "Portal sources selected, starting capture...";
    startCapture();
}

void ScreenCastPortal::startCapture()
{
    QString reqToken = nextRequestToken();
    QString reqPath = requestObjectPath(reqToken);

    QDBusConnection bus = QDBusConnection::sessionBus();
    bus.connect("", reqPath, PORTAL_REQUEST_IF, "Response",
                this, SLOT(onStartResponse(QDBusMessage)));

    QDBusMessage msg = QDBusMessage::createMethodCall(
        PORTAL_SERVICE, PORTAL_PATH, PORTAL_SCREENCAST_IF, "Start");

    QVariantMap options;
    options["handle_token"] = reqToken;

    msg << QVariant::fromValue(QDBusObjectPath(m_sessionHandle));
    msg << QString("");
    msg << QVariant::fromValue(options);

    QDBusPendingCall pending = bus.asyncCall(msg);
    Q_UNUSED(pending);
}

void ScreenCastPortal::onStartResponse(const QDBusMessage &msg)
{
    QDBusConnection::sessionBus().disconnect(
        "", msg.path(), PORTAL_REQUEST_IF, "Response",
        this, SLOT(onStartResponse(QDBusMessage)));

    uint response = msg.arguments().at(0).toUInt();
    if (response != 0) {
        emit streamFailed(QString("Start denied by user (code %1)").arg(response));
        return;
    }

    QDBusArgument resultsArg = msg.arguments().at(1).value<QDBusArgument>();
    QVariantMap results;
    resultsArg >> results;

    // Extract PipeWire node ID from the streams array: a(ua{sv})
    if (results.contains("streams")) {
        const QDBusArgument streamsArg =
            results["streams"].value<QDBusArgument>();
        streamsArg.beginArray();
        if (!streamsArg.atEnd()) {
            streamsArg.beginStructure();
            streamsArg >> m_pipewireNode;
            QVariantMap streamProps;
            streamsArg >> streamProps;
            streamsArg.endStructure();
        }
        streamsArg.endArray();
    }

    // Extract and persist the new restore token
    if (results.contains("restore_token")) {
        QVariant tokenVar = results["restore_token"];
        QString newToken = tokenVar.toString();
        if (!newToken.isEmpty()) {
            m_restoreToken = newToken;
            saveRestoreToken();
            qDebug() << "Saved new restore token for future sessions";
        }
    }

    qDebug() << "Portal started, PipeWire node:" << m_pipewireNode;
    openPipeWireRemote();
}

void ScreenCastPortal::openPipeWireRemote()
{
    QDBusMessage msg = QDBusMessage::createMethodCall(
        PORTAL_SERVICE, PORTAL_PATH, PORTAL_SCREENCAST_IF,
        "OpenPipeWireRemote");

    msg << QVariant::fromValue(QDBusObjectPath(m_sessionHandle));
    msg << QVariant::fromValue(QVariantMap());

    QDBusMessage reply = QDBusConnection::sessionBus().call(msg);
    if (reply.type() == QDBusMessage::ErrorMessage) {
        emit streamFailed("OpenPipeWireRemote failed: " + reply.errorMessage());
        return;
    }

    QDBusUnixFileDescriptor unixFd =
        reply.arguments().at(0).value<QDBusUnixFileDescriptor>();
    if (!unixFd.isValid()) {
        emit streamFailed("Received invalid PipeWire file descriptor");
        return;
    }

    int fd = fcntl(unixFd.fileDescriptor(), F_DUPFD_CLOEXEC, 3);
    if (fd < 0) {
        emit streamFailed("Failed to duplicate PipeWire fd");
        return;
    }

    qDebug() << "Got PipeWire fd:" << fd;
    initPipeWire(fd, m_pipewireNode);
}

// --- PipeWire setup / teardown ---

void ScreenCastPortal::initPipeWire(int fd, uint32_t nodeId)
{
    static struct pw_stream_events s_streamEvents = {};
    s_streamEvents.version       = PW_VERSION_STREAM_EVENTS;
    s_streamEvents.state_changed = onPwStreamStateChanged;
    s_streamEvents.param_changed = onPwStreamParamChanged;
    s_streamEvents.process       = onPwStreamProcess;

    m_pwLoop = pw_thread_loop_new("MonitorClient PipeWire", nullptr);
    if (!m_pwLoop) {
        close(fd);
        emit streamFailed("Failed to create PipeWire thread loop");
        return;
    }

    m_pwContext = pw_context_new(
        pw_thread_loop_get_loop(m_pwLoop), nullptr, 0);
    if (!m_pwContext) {
        close(fd);
        pw_thread_loop_destroy(m_pwLoop);
        m_pwLoop = nullptr;
        emit streamFailed("Failed to create PipeWire context");
        return;
    }

    if (pw_thread_loop_start(m_pwLoop) < 0) {
        close(fd);
        pw_context_destroy(m_pwContext);
        m_pwContext = nullptr;
        pw_thread_loop_destroy(m_pwLoop);
        m_pwLoop = nullptr;
        emit streamFailed("Failed to start PipeWire thread loop");
        return;
    }

    pw_thread_loop_lock(m_pwLoop);

    m_pwCore = pw_context_connect_fd(m_pwContext, fd, nullptr, 0);
    if (!m_pwCore) {
        pw_thread_loop_unlock(m_pwLoop);
        emit streamFailed("Failed to connect PipeWire core via fd");
        return;
    }

    auto *props = pw_properties_new(
        PW_KEY_MEDIA_TYPE, "Video",
        PW_KEY_MEDIA_CATEGORY, "Capture",
        PW_KEY_MEDIA_ROLE, "Screen",
        nullptr);

    m_pwStream = pw_stream_new(m_pwCore, "MonitorClient Screen Capture", props);
    if (!m_pwStream) {
        pw_core_disconnect(m_pwCore);
        m_pwCore = nullptr;
        pw_thread_loop_unlock(m_pwLoop);
        emit streamFailed("Failed to create PipeWire stream");
        return;
    }

    pw_stream_add_listener(m_pwStream, &m_streamListener,
                           &s_streamEvents, this);

    // Build SPA format parameters — prefer BGRx (maps directly to QImage::Format_RGB32)
    uint8_t buf[1024];
    struct spa_pod_builder b = SPA_POD_BUILDER_INIT(buf, sizeof(buf));
    const struct spa_pod *params[1];

    struct spa_rectangle default_size = {1920, 1080};
    struct spa_rectangle min_size     = {1, 1};
    struct spa_rectangle max_size     = {8192, 4320};
    struct spa_fraction  default_rate = {1, 1};
    struct spa_fraction  min_rate     = {0, 1};
    struct spa_fraction  max_rate     = {30, 1};

    params[0] = (const struct spa_pod *)spa_pod_builder_add_object(&b,
        SPA_TYPE_OBJECT_Format, SPA_PARAM_EnumFormat,
        SPA_FORMAT_mediaType,       SPA_POD_Id(SPA_MEDIA_TYPE_video),
        SPA_FORMAT_mediaSubtype,    SPA_POD_Id(SPA_MEDIA_SUBTYPE_raw),
        SPA_FORMAT_VIDEO_format,    SPA_POD_CHOICE_ENUM_Id(6,
            SPA_VIDEO_FORMAT_BGRx,
            SPA_VIDEO_FORMAT_BGRx,
            SPA_VIDEO_FORMAT_BGRA,
            SPA_VIDEO_FORMAT_RGBx,
            SPA_VIDEO_FORMAT_RGBA,
            SPA_VIDEO_FORMAT_RGB),
        SPA_FORMAT_VIDEO_size,      SPA_POD_CHOICE_RANGE_Rectangle(
            &default_size, &min_size, &max_size),
        SPA_FORMAT_VIDEO_framerate, SPA_POD_CHOICE_RANGE_Fraction(
            &default_rate, &min_rate, &max_rate));

    int ret = pw_stream_connect(
        m_pwStream, PW_DIRECTION_INPUT, nodeId,
        static_cast<pw_stream_flags>(
            PW_STREAM_FLAG_AUTOCONNECT | PW_STREAM_FLAG_MAP_BUFFERS),
        params, 1);

    pw_thread_loop_unlock(m_pwLoop);

    if (ret < 0) {
        emit streamFailed(QString("pw_stream_connect failed: %1")
                              .arg(spa_strerror(ret)));
    } else {
        qDebug() << "PipeWire stream connecting to node" << nodeId;
    }
}

void ScreenCastPortal::cleanupPipeWire()
{
    if (!m_pwLoop)
        return;

    pw_thread_loop_stop(m_pwLoop);

    if (m_pwStream) {
        spa_hook_remove(&m_streamListener);
        pw_stream_destroy(m_pwStream);
        m_pwStream = nullptr;
    }

    if (m_pwCore) {
        pw_core_disconnect(m_pwCore);
        m_pwCore = nullptr;
    }

    if (m_pwContext) {
        pw_context_destroy(m_pwContext);
        m_pwContext = nullptr;
    }

    pw_thread_loop_destroy(m_pwLoop);
    m_pwLoop = nullptr;
}

// --- Helpers ---

QString ScreenCastPortal::portalSenderName()
{
    QString name = QDBusConnection::sessionBus().baseService();
    name.remove(':');
    name.replace('.', '_');
    return name;
}

QString ScreenCastPortal::nextRequestToken()
{
    return QString("mc_%1").arg(++m_tokenCounter);
}

QString ScreenCastPortal::requestObjectPath(const QString &token)
{
    return QString("/org/freedesktop/portal/desktop/request/%1/%2")
        .arg(portalSenderName(), token);
}

uint32_t ScreenCastPortal::portalVersion()
{
    QDBusInterface iface(PORTAL_SERVICE, PORTAL_PATH,
                         PORTAL_SCREENCAST_IF,
                         QDBusConnection::sessionBus());
    if (!iface.isValid())
        return 0;
    return iface.property("version").toUInt();
}

void ScreenCastPortal::loadRestoreToken()
{
    const QString path=QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)
        +QStringLiteral("/monitorclient/settings.ini");
    QDir().mkpath(QFileInfo(path).absolutePath());
    QSettings settings(path,QSettings::IniFormat);
    m_restoreToken = settings.value("ScreenCast/restore_token", "").toString();
    if (!m_restoreToken.isEmpty())
        qDebug() << "Loaded restore token from settings";
}

void ScreenCastPortal::saveRestoreToken()
{
    const QString path=QStandardPaths::writableLocation(QStandardPaths::ConfigLocation)
        +QStringLiteral("/monitorclient/settings.ini");
    QDir().mkpath(QFileInfo(path).absolutePath());
    QSettings settings(path,QSettings::IniFormat);
    settings.setValue("ScreenCast/restore_token", m_restoreToken);
    settings.sync();
}
