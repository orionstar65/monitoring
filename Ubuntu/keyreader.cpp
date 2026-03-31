/*
 * monitorclient-keyreader — setgid helper for evdev keyboard capture.
 *
 * Installed as root:input with mode 2755.  Opens /dev/input/event* nodes,
 * reads key-press events, and writes one JSON object per line to stdout:
 *
 *   {"ts":"2026-03-31 10:00:00","key":"a"}
 *
 * MonitorClient spawns this as a child process and reads its stdout.
 * The main app never needs the "input" group itself.
 *
 * Build: gcc -O2 -o monitorclient-keyreader keyreader.cpp
 *        (no Qt / no X11 — pure C with tiny bits of POSIX)
 */

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cerrno>
#include <ctime>
#include <vector>
#include <string>

#include <dirent.h>
#include <fcntl.h>
#include <linux/input.h>
#include <sys/ioctl.h>
#include <sys/select.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

struct Device {
    int fd;
    std::string path;
    std::string name;
    bool shiftL = false, shiftR = false;
    bool ctrlL  = false, ctrlR  = false;
    bool altL   = false, altR   = false;
    bool superL = false, superR = false;
    bool caps   = false;
};

static bool isKeyboard(int fd)
{
    unsigned char evbit[(EV_MAX / 8) + 1] = {};
    if (ioctl(fd, EVIOCGBIT(0, sizeof(evbit)), evbit) < 0)
        return false;
    if (!(evbit[EV_KEY / 8] & (1 << (EV_KEY % 8))))
        return false;

    unsigned char keybit[(KEY_MAX / 8) + 1] = {};
    if (ioctl(fd, EVIOCGBIT(EV_KEY, sizeof(keybit)), keybit) < 0)
        return false;

    auto has = [&](int k) -> bool { return keybit[k / 8] & (1 << (k % 8)); };
    int letters = 0;
    for (int k = KEY_Q; k <= KEY_P; ++k) letters += has(k) ? 1 : 0;
    for (int k = KEY_A; k <= KEY_L; ++k) letters += has(k) ? 1 : 0;
    for (int k = KEY_Z; k <= KEY_M; ++k) letters += has(k) ? 1 : 0;
    return letters >= 20;
}

static std::vector<Device> openKeyboards()
{
    std::vector<Device> devs;
    DIR *dir = opendir("/dev/input");
    if (!dir) return devs;

    struct dirent *ent;
    while ((ent = readdir(dir)) != nullptr) {
        if (strncmp(ent->d_name, "event", 5) != 0) continue;
        std::string path = std::string("/dev/input/") + ent->d_name;
        int fd = open(path.c_str(), O_RDONLY | O_NONBLOCK);
        if (fd < 0) {
            fprintf(stderr, "[keyreader] open %s: %s\n", path.c_str(), strerror(errno));
            continue;
        }
        if (!isKeyboard(fd)) { close(fd); continue; }

        char name[256] = "Unknown";
        ioctl(fd, EVIOCGNAME(sizeof(name)), name);
        fprintf(stderr, "[keyreader] opened %s (%s)\n", path.c_str(), name);
        devs.push_back({fd, path, name});
    }
    closedir(dir);
    return devs;
}

static const char *keyName(int code, bool shift, bool caps)
{
    bool upper = shift != caps;
    switch (code) {
    case KEY_ESC: return "Escape";
    case KEY_1: return shift ? "!" : "1";
    case KEY_2: return shift ? "@" : "2";
    case KEY_3: return shift ? "#" : "3";
    case KEY_4: return shift ? "$" : "4";
    case KEY_5: return shift ? "%" : "5";
    case KEY_6: return shift ? "^" : "6";
    case KEY_7: return shift ? "&" : "7";
    case KEY_8: return shift ? "*" : "8";
    case KEY_9: return shift ? "(" : "9";
    case KEY_0: return shift ? ")" : "0";
    case KEY_MINUS: return shift ? "_" : "-";
    case KEY_EQUAL: return shift ? "+" : "=";
    case KEY_BACKSPACE: return "Backspace";
    case KEY_TAB: return "Tab";
    case KEY_Q: return upper ? "Q" : "q";
    case KEY_W: return upper ? "W" : "w";
    case KEY_E: return upper ? "E" : "e";
    case KEY_R: return upper ? "R" : "r";
    case KEY_T: return upper ? "T" : "t";
    case KEY_Y: return upper ? "Y" : "y";
    case KEY_U: return upper ? "U" : "u";
    case KEY_I: return upper ? "I" : "i";
    case KEY_O: return upper ? "O" : "o";
    case KEY_P: return upper ? "P" : "p";
    case KEY_LEFTBRACE: return shift ? "{" : "[";
    case KEY_RIGHTBRACE: return shift ? "}" : "]";
    case KEY_ENTER: return "Enter";
    case KEY_A: return upper ? "A" : "a";
    case KEY_S: return upper ? "S" : "s";
    case KEY_D: return upper ? "D" : "d";
    case KEY_F: return upper ? "F" : "f";
    case KEY_G: return upper ? "G" : "g";
    case KEY_H: return upper ? "H" : "h";
    case KEY_J: return upper ? "J" : "j";
    case KEY_K: return upper ? "K" : "k";
    case KEY_L: return upper ? "L" : "l";
    case KEY_SEMICOLON: return shift ? ":" : ";";
    case KEY_APOSTROPHE: return shift ? "\"" : "'";
    case KEY_GRAVE: return shift ? "~" : "`";
    case KEY_BACKSLASH: return shift ? "|" : "\\";
    case KEY_Z: return upper ? "Z" : "z";
    case KEY_X: return upper ? "X" : "x";
    case KEY_C: return upper ? "C" : "c";
    case KEY_V: return upper ? "V" : "v";
    case KEY_B: return upper ? "B" : "b";
    case KEY_N: return upper ? "N" : "n";
    case KEY_M: return upper ? "M" : "m";
    case KEY_COMMA: return shift ? "<" : ",";
    case KEY_DOT: return shift ? ">" : ".";
    case KEY_SLASH: return shift ? "?" : "/";
    case KEY_KPASTERISK: return "*";
    case KEY_SPACE: return " ";
    case KEY_F1: return "F1";  case KEY_F2: return "F2";
    case KEY_F3: return "F3";  case KEY_F4: return "F4";
    case KEY_F5: return "F5";  case KEY_F6: return "F6";
    case KEY_F7: return "F7";  case KEY_F8: return "F8";
    case KEY_F9: return "F9";  case KEY_F10: return "F10";
    case KEY_F11: return "F11"; case KEY_F12: return "F12";
    case KEY_NUMLOCK: return "Num Lock";
    case KEY_SCROLLLOCK: return "Scroll Lock";
    case KEY_KP7: return "7"; case KEY_KP8: return "8"; case KEY_KP9: return "9";
    case KEY_KPMINUS: return "-";
    case KEY_KP4: return "4"; case KEY_KP5: return "5"; case KEY_KP6: return "6";
    case KEY_KPPLUS: return "+";
    case KEY_KP1: return "1"; case KEY_KP2: return "2"; case KEY_KP3: return "3";
    case KEY_KP0: return "0"; case KEY_KPDOT: return ".";
    case KEY_SYSRQ: return "Print Screen";
    case KEY_PAUSE: return "Pause";
    case KEY_KPENTER: return "Enter";
    case KEY_KPSLASH: return "/";
    case KEY_HOME: return "Home";     case KEY_UP: return "Up";
    case KEY_PAGEUP: return "Page Up"; case KEY_LEFT: return "Left";
    case KEY_RIGHT: return "Right";   case KEY_END: return "End";
    case KEY_DOWN: return "Down";     case KEY_PAGEDOWN: return "Page Down";
    case KEY_INSERT: return "Insert"; case KEY_DELETE: return "Delete";
    case KEY_MUTE: return "Mute";
    case KEY_VOLUMEDOWN: return "Volume Down";
    case KEY_VOLUMEUP: return "Volume Up";
    case KEY_POWER: return "Power";
    case KEY_KPEQUAL: return "=";
    case KEY_KPCOMMA: return ",";
    case KEY_LEFTMETA: return "Super";
    case KEY_RIGHTMETA: return "Super";
    case KEY_COMPOSE: return "Menu";
    case KEY_STOP: return "Stop";
    case KEY_UNDO: return "Undo";
    case KEY_COPY: return "Copy";
    case KEY_CUT: return "Cut";
    case KEY_PASTE: return "Paste";
    case KEY_FIND: return "Find";
    case KEY_HELP: return "Help";
    case KEY_SLEEP: return "Sleep";
    case KEY_WAKEUP: return "Wake Up";
    case KEY_MEDIA: return "Media";
    case KEY_MAIL: return "Mail";
    case KEY_HOMEPAGE: return "Home Page";
    case KEY_CALC: return "Calculator";
    case KEY_COFFEE: return "Screen Lock";
    case KEY_BACK: return "Back";
    case KEY_FORWARD: return "Forward";
    case KEY_NEXTSONG: return "Next Track";
    case KEY_PREVIOUSSONG: return "Previous Track";
    case KEY_PLAYPAUSE: return "Play/Pause";
    case KEY_STOPCD: return "Stop Media";
    case KEY_BRIGHTNESSDOWN: return "Brightness Down";
    case KEY_BRIGHTNESSUP: return "Brightness Up";
    case KEY_SWITCHVIDEOMODE: return "Display Switch";
    case KEY_WLAN: return "WiFi";
    case KEY_BLUETOOTH: return "Bluetooth";
    case KEY_RFKILL: return "Airplane Mode";
    case KEY_F13: return "F13"; case KEY_F14: return "F14";
    case KEY_F15: return "F15"; case KEY_F16: return "F16";
    case KEY_F17: return "F17"; case KEY_F18: return "F18";
    case KEY_F19: return "F19"; case KEY_F20: return "F20";
    case KEY_F21: return "F21"; case KEY_F22: return "F22";
    case KEY_F23: return "F23"; case KEY_F24: return "F24";
    default: return nullptr;
    }
}

static void jsonEscape(const char *src, char *dst, size_t dstLen)
{
    size_t j = 0;
    for (size_t i = 0; src[i] && j + 6 < dstLen; ++i) {
        unsigned char c = (unsigned char)src[i];
        if (c == '"')       { dst[j++]='\\'; dst[j++]='"'; }
        else if (c == '\\') { dst[j++]='\\'; dst[j++]='\\'; }
        else if (c < 0x20)  { j += snprintf(dst+j, dstLen-j, "\\u%04x", c); }
        else                { dst[j++] = c; }
    }
    dst[j] = '\0';
}

static void emitKey(const char *key)
{
    time_t now = time(nullptr);
    struct tm tm;
    localtime_r(&now, &tm);
    char ts[32];
    strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &tm);
    char escaped[512];
    jsonEscape(key, escaped, sizeof(escaped));
    printf("{\"ts\":\"%s\",\"key\":\"%s\"}\n", ts, escaped);
    fflush(stdout);
}

int main()
{
    setvbuf(stdout, nullptr, _IOLBF, 0);

    auto devs = openKeyboards();
    if (devs.empty()) {
        fprintf(stderr, "[keyreader] no keyboard devices opened\n");
        return 1;
    }
    fprintf(stderr, "[keyreader] monitoring %zu device(s)\n", devs.size());

    for (;;) {
        fd_set fds;
        FD_ZERO(&fds);
        int maxfd = -1;
        for (auto &d : devs) {
            if (d.fd >= 0) { FD_SET(d.fd, &fds); if (d.fd > maxfd) maxfd = d.fd; }
        }
        if (maxfd < 0) break;

        struct timeval tv = {0, 200000};
        int r = select(maxfd + 1, &fds, nullptr, nullptr, &tv);
        if (r < 0) { if (errno == EINTR) continue; break; }
        if (r == 0) continue;

        for (auto &d : devs) {
            if (d.fd < 0 || !FD_ISSET(d.fd, &fds)) continue;
            struct input_event ev;
            while (read(d.fd, &ev, sizeof(ev)) == sizeof(ev)) {
                if (ev.type != EV_KEY) continue;
                int code = ev.code;
                bool press = (ev.value == 1);

                if (code == KEY_LEFTSHIFT)  { d.shiftL = press; continue; }
                if (code == KEY_RIGHTSHIFT) { d.shiftR = press; continue; }
                if (code == KEY_LEFTCTRL)   { d.ctrlL  = press; continue; }
                if (code == KEY_RIGHTCTRL)  { d.ctrlR  = press; continue; }
                if (code == KEY_LEFTALT)    { d.altL   = press; continue; }
                if (code == KEY_RIGHTALT)   { d.altR   = press; continue; }
                if (code == KEY_LEFTMETA)   { d.superL = press; continue; }
                if (code == KEY_RIGHTMETA)  { d.superR = press; continue; }
                if (code == KEY_CAPSLOCK && press) { d.caps = !d.caps; continue; }

                if (!press) continue;

                bool shift = d.shiftL || d.shiftR;
                const char *name = keyName(code, shift, d.caps);

                std::string out;
                if (d.ctrlL || d.ctrlR)   out += "Ctrl + ";
                if (d.altL  || d.altR)    out += "Alt + ";
                if (d.superL || d.superR) out += "Super + ";
                if (shift)                out += "Shift + ";

                if (name) out += name;
                else { char buf[32]; snprintf(buf, sizeof(buf), "Key_%d", code); out += buf; }

                emitKey(out.c_str());
            }
        }
    }

    for (auto &d : devs) if (d.fd >= 0) close(d.fd);
    return 0;
}
