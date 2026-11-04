//
//  AppDelegate.swift
//  MonitorClient
//
//  Created by Uranus on 7/5/25.
//  Updated by Uranus on 7/6/25.
//

import SQLite3
import Cocoa
import CoreGraphics
import IOKit
import IOKit.usb
import os.log
import UserNotifications
import Security

let CHECKER_IDENTIFIER = "com.airentech.MonitorClient"

let API_ROUTE = "/webapi.php"
let TIC_ROUTE = "/eventhandler.php"
var APP_VERSION = "1.5"

// Configuration variables (will be updated from API)
var TIME_INTERVAL = 60      // Screenshots every 60 seconds (default)
var TIC_INTERVAL = 300      // Tic every 5 minutes (default)
var HISTORY_INTERVAL = 600  // History every 10 minutes (default)
var KEY_INTERVAL = 300      // Keys every 5 minutes (default)
var USB_INTERVAL = 300      // USB monitoring every 5 minutes (default)
var CONFIG_REFRESH_INTERVAL = 300 // Config refresh every 5 minutes (default)

// Current configuration
var currentConfig: ClientConfig?
var lastConfigUpdate: Date?
var timer: Timer? = nil
var macAddress: String = ""
var activeRunning: Bool = false
var safariChecked: Double? = nil
var chromeChecked: Int64? = nil
var firefoxChecked: Int64? = nil
var edgeChecked: Int64? = nil
var operaChecked: Int64? = nil
var yandexChecked: Int64? = nil
var vivaldiChecked: Int64? = nil
var braveChecked: Int64? = nil
var lastScreenshotCheck: Date = Date()
var lastHistoryCheck: Date = Date()
var lastTicCheck: Date = Date()
var lastKeyCheck: Date = Date()
var lastUSBCheck: Date = Date()

// Browser bundle identifiers
private let browserBundleIDs = [
    "com.apple.Safari",
    "com.google.Chrome",
    "org.mozilla.firefox",
    "com.microsoft.edgemac",
    "com.operasoftware.Opera",
    "ru.yandex.desktop.yandex-browser",
    "com.vivaldi.Vivaldi",
    "com.brave.Browser"
]

struct BrowserHistoryLog: Codable {
    let browser: String
    let url: String
    let title: String
    let last_visit: Int64
    let date: String
}

struct KeyLog: Codable {
    let date: String
    let application: String
    let key: String
}

struct USBDeviceLog: Codable {
    let date: String
    let device_name: String
    let device_path: String
    let device_type: String
    let action: String
}

// Configuration structures
struct ClientConfig: Codable {
    let intervals: IntervalsConfig
    let server: ServerConfig
    let features: FeaturesConfig
    let platform: String
    let version: String
    let last_updated: String
    let client_info: ClientInfo?
}

struct IntervalsConfig: Codable {
    let screenshot: Int
    let tic: Int
    let history: Int
    let keylog: Int
    let usb: Int
    let config_refresh: Int
}

struct ServerConfig: Codable {
    let max_retry_attempts: Int
    let connection_timeout: Int
    let request_timeout: Int
    let chunk_size: Int
    let compression_enabled: Bool
}

struct FeaturesConfig: Codable {
    let screenshot_enabled: Bool
    let browser_history_enabled: Bool
    let keylog_enabled: Bool
    let usb_monitoring_enabled: Bool
    let debug_logging_enabled: Bool
    let auto_update_enabled: Bool
}

struct ClientInfo: Codable {
    let ip: String
    let user_agent: String
    let timestamp: Int
    let request_id: String
}

struct ConfigResponse: Codable {
    let success: Bool
    let data: ClientConfig?
    let error: String?
}

// Global callback function for CGEvent tap
func handleKeyDownCallback(
    proxy: CGEventTapProxy,
    type: CGEventType,
    event: CGEvent,
    refcon: UnsafeMutableRawPointer?
) -> Unmanaged<CGEvent>? {
    // Only handle keyDown events
    guard type == .keyDown else {
        return Unmanaged.passUnretained(event)
    }
    
    // Get the AppDelegate instance from the refcon
    guard let appDelegate = Unmanaged<AppDelegate>.fromOpaque(refcon!).takeUnretainedValue() as? AppDelegate else {
        return Unmanaged.passUnretained(event)
    }
    
    // Update last key capture time
    appDelegate.lastKeyCaptureTime = Date()
    
    // Safely create NSEvent from CGEvent
    guard let nsEvent = NSEvent(cgEvent: event) else {
        return Unmanaged.passUnretained(event)
    }
    
    // Get the active application
    let activeApp = NSWorkspace.shared.frontmostApplication
    let appName = activeApp?.localizedName ?? "Unknown"
    let bundleId = activeApp?.bundleIdentifier ?? "Unknown"
    
    // Get key information
    let keyCode = event.getIntegerValueField(.keyboardEventKeycode)
    let modifiers = appDelegate.checkModifierKeys(event)
    let pressedChar = nsEvent.charactersIgnoringModifiers ?? ""
    let currentDate = appDelegate.getCurrentDateTimeString()
    
    // Get readable key name for special keys
    let keyName = appDelegate.getKeyName(keyCode: Int(keyCode), character: pressedChar)
    
    let keyInfo = "\(appName) (\(bundleId)) \(modifiers)\(keyName)"
    appDelegate.logMessage("Key captured: \(keyInfo)", level: .debug)
    appDelegate.keyLogs.append(KeyLog(date: currentDate, application: "\(appName) (\(bundleId))", key: "\(modifiers)\(keyName)"))
    
    // Log key log count for debugging
    if appDelegate.keyLogs.count % 10 == 0 {
        appDelegate.logMessage("Key log count: \(appDelegate.keyLogs.count)", level: .debug)
    }
    
    return Unmanaged.passUnretained(event)
}

private final class MonitorPickerCoordinator: NSObject {
    let picker: NSPopUpButton
    let label: NSTextField
    let remark: NSTextField
    let os: NSPopUpButton
    let machine: NSPopUpButton
    let monitors: [[String: Any]]
    init(_ picker: NSPopUpButton, _ label: NSTextField, _ remark: NSTextField,
         _ os: NSPopUpButton, _ machine: NSPopUpButton, _ monitors: [[String: Any]]) {
        self.picker = picker; self.label = label; self.remark = remark
        self.os = os; self.machine = machine; self.monitors = monitors
    }
    @objc func update() {
        let id = picker.selectedItem?.representedObject as? Int ?? 0
        guard let monitor = monitors.first(where: { ($0["id"] as? Int ?? -1) == id }), id > 0 else {
            label.stringValue = ""; remark.stringValue = ""
            os.selectItem(withTitle: "Mac"); machine.selectItem(withTitle: "Laptop")
            return
        }
        label.stringValue = monitor["name"] as? String ?? ""
        remark.stringValue = monitor["remark"] as? String ?? ""
        os.selectItem(withTitle: monitor["platform"] as? String ?? "Mac")
        let type = monitor["type"] as? String ?? ""
        machine.selectItem(withTitle: type.lowercased() == "desktop" ? "Desktop" : "Laptop")
    }
}


private final class MonitorSettingsDocumentView: NSView {
    // NSScrollView's unflipped document starts at its bottom, leaving a large
    // blank strip above the first control. Pin settings content to the top.
    override var isFlipped: Bool { true }
}

private final class UnifiedWindowActions: NSObject, NSWindowDelegate {
    weak var window: NSWindow?
    var handler: ((String) -> Void)?
    @objc func activate(_ sender: NSButton) { handler?(sender.identifier?.rawValue ?? "") }
    @objc func selectMonitor(_ sender: NSPopUpButton) { handler?("monitor") }
    @objc func selectUser(_ sender: NSPopUpButton) { handler?("user") }
    func windowWillClose(_ notification: Notification) { NSApp.stopModal(withCode: .cancel) }
}

class AppDelegate: NSObject, NSApplicationDelegate, UNUserNotificationCenterDelegate {
    
    var storage: UserDefaults!
    private var clientToken: String = ""
    private var selectedMonitorId: Int = 0
    private var notificationsEnabled: Bool {
        get { storage.object(forKey: "notifications-enabled") as? Bool ?? true }
        set { storage.set(newValue, forKey: "notifications-enabled") }
    }
    private let keychainService = "com.airentech.monitorclient.v15.device"
    private func sessionAccount() -> String {
        let ip = storage.string(forKey: "server-ip") ?? ""
        let port = storage.string(forKey: "server-port") ?? "8924"
        return ip + ":" + port
    }
    private func keychainSaveToken(_ token: String) {
        let account = sessionAccount()
        let base: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: keychainService, kSecAttrAccount as String: account]
        SecItemDelete(base as CFDictionary)
        var item = base
        item[kSecValueData as String] = Data(token.utf8)
        item[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        let status = SecItemAdd(item as CFDictionary, nil)
        if status != errSecSuccess {
            showClientNotification("Session not saved", "Keychain is unavailable; select a User No again after restarting.")
        }
    }
    private func keychainLoadToken() -> String? {
        var query: [String: Any] = [kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: keychainService,
            kSecAttrAccount as String: sessionAccount(),
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne]
        var item: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &item) == errSecSuccess,
              let data = item as? Data else { return nil }
        return String(data: data, encoding: .utf8)
    }
    // Centralized user notifications; honors the notification switch in Settings.
    private func deliverNotification(_ content: UNMutableNotificationContent) {
        guard notificationsEnabled else { return }
        let request = UNNotificationRequest(
            identifier: UUID().uuidString,
            content: content,
            trigger: nil
        )
        UNUserNotificationCenter.current().add(request) { error in
            if let error = error {
                NSLog("MonitorClient: notification delivery failed: %@", error.localizedDescription)
            }
        }
    }

    private func showClientNotification(_ title: String, _ message: String) {
        let notification = UNMutableNotificationContent()
        notification.title = title
        notification.body = message
        self.deliverNotification(notification)
    }

    private func configureNotifications() {
        let center = UNUserNotificationCenter.current()
        center.delegate = self
        let accessibility = UNNotificationAction(
            identifier: "OPEN_ACCESSIBILITY_SETTINGS",
            title: "Open Settings",
            options: [.foreground]
        )
        let recording = UNNotificationAction(
            identifier: "OPEN_SCREEN_RECORDING_SETTINGS",
            title: "Open Settings",
            options: [.foreground]
        )
        center.setNotificationCategories([
            UNNotificationCategory(
                identifier: "MONITOR_ACCESSIBILITY",
                actions: [accessibility],
                intentIdentifiers: [],
                options: []
            ),
            UNNotificationCategory(
                identifier: "MONITOR_SCREEN_RECORDING",
                actions: [recording],
                intentIdentifiers: [],
                options: []
            )
        ])
        center.requestAuthorization(options: [.alert, .sound]) { _, error in
            if let error = error {
                NSLog("MonitorClient: notification permission error: %@", error.localizedDescription)
            }
        }
    }
    private func restoreRememberedSession() {
        guard let token = keychainLoadToken(), !token.isEmpty else {
            openLoginDialog()
            return
        }
        clientToken = token
        guard let result = clientAuthRequest(["action": "restore"], authenticated: true),
              result["success"] as? Bool == true else {
            clientToken = ""
            selectedMonitorId = 0
            openLoginDialog()
            return
        }
        selectedMonitorId = result["monitor_id"] as? Int ?? 0
        if let no = (result["user"] as? [String: Any])?["user_no"] {
            storage.set(String(describing: no), forKey: "user_no")
        }
        if selectedMonitorId > 0 { startKeyboardMonitoring() }
        else { showClientNotification("Monitor setup required", "Choose an assigned monitor in Settings.") }
        updateTrayStatusDot()
    }
    // The app icon carries a small 6.5pt red/green status dot at bottom-right.
    // No separate emoji or enlarged status indicator is rendered in the menu bar.
    private func makeTrayIcon(active: Bool) -> NSImage {
        let image = NSImage(size: NSSize(width: 22, height: 22), flipped: false) { _ in
            // Keep the existing notepad glyph, not the application icon.
            let notepad = NSAttributedString(string: "🗒️", attributes: [
                .font: NSFont.systemFont(ofSize: 17)
            ])
            notepad.draw(in: NSRect(x: 1.5, y: 1.5, width: 19, height: 19))
            let badge = NSBezierPath(ovalIn: NSRect(x: 15.5, y: 0.75, width: 6.5, height: 6.5))
            (active ? NSColor.systemGreen : NSColor.systemRed).setFill()
            badge.fill()
            NSColor.white.setStroke()
            badge.lineWidth = 1.1
            badge.stroke()
            return true
        }
        image.isTemplate = false
        return image
    }

    private lazy var onlineTrayIcon = makeTrayIcon(active: true)
    private lazy var offlineTrayIcon = makeTrayIcon(active: false)

    private func updateTrayStatusDot() {
        if !Thread.isMainThread {
            DispatchQueue.main.async { [weak self] in self?.updateTrayStatusDot() }
            return
        }
        let active = !clientToken.isEmpty && selectedMonitorId > 0 && isServerConnected
        statusBarItem?.button?.image = active ? onlineTrayIcon : offlineTrayIcon
        statusBarItem?.button?.title = ""
        statusBarItem?.button?.imagePosition = .imageOnly
        statusBarItem?.button?.toolTip = active
            ? "MonitorClient v1.5 · Active"
            : "MonitorClient v1.5 · Offline, no user selected or unconfigured"
    }
    var eventTap: CFMachPort?
    var keyLogs: [KeyLog]!
    var usbDeviceLogs: [USBDeviceLog]!
    var usbNotificationPort: IONotificationPortRef?
    var usbAddedIterator: io_iterator_t = 0
    var usbRemovedIterator: io_iterator_t = 0
    
    // Status bar menu components
    var statusBarItem: NSStatusItem?
    var statusMenu: NSMenu?
    var statusUpdateTimer: Timer?

    // Add these properties at the top of the class
    private var lastEventTapCheck: Date = Date()
    private var eventTapCheckInterval: TimeInterval = 30.0 // Check every 30 seconds (optimized from 3)
    private var isReestablishingEventTap: Bool = false
    var lastKeyCaptureTime: Date = Date()
    private let maxKeyCaptureGap: TimeInterval = 60.0 // Alert if no keys for 60 seconds (optimized from 10)
    
    // Last sent times for status tracking
    private var lastKeyLogSent: Date?
    private var lastBrowserHistorySent: Date?
    private var lastUSBLogSent: Date?
    private var lastScreenshotSent: Date?
    private var lastTicSent: Date?
    
    // Server connectivity tracking
    private var isServerConnected: Bool = false
    private var wasServerConnected: Bool = false // Track previous state for notifications
    
    // Client information (cached)
    private var clientIPAddress: String = "Unknown"

    // Configuration management
    private var configTimer: Timer?
    private var isConfigUpdating: Bool = false

    // Logging system (optimized with buffering)
    let logFile = "MonitorClient.log"
    var logFileHandle: FileHandle?
    private var logBuffer: [String] = []
    private var logFlushTimer: Timer?
    private let maxLogBufferSize = 50

    // MARK: - Logging Functions
    
    private func setupLogging() {
        // Determine if we're in development or production mode
        let isDevelopment = Bundle.main.bundlePath.contains("DerivedData") || Bundle.main.bundlePath.contains("Debug")
        
        var logURL: URL?
        var logFileCreated = false
        
        if isDevelopment {
            // Development mode: save to local folder (current directory)
            let currentDir = FileManager.default.currentDirectoryPath
            logURL = URL(fileURLWithPath: currentDir).appendingPathComponent(logFile)
            
            do {
                if !FileManager.default.fileExists(atPath: logURL!.path) {
                    try "".write(to: logURL!, atomically: true, encoding: .utf8)
                }
                try "test".write(to: logURL!, atomically: true, encoding: .utf8)
                logFileCreated = true
                // Development mode: Log file created
            } catch {
                // Failed to create log file in development mode
            }
        } else {
            // Production mode: save to Applications folder
            let appBundlePath = Bundle.main.bundlePath
            let appContainerURL = URL(fileURLWithPath: appBundlePath).deletingLastPathComponent()
            let logDirPath = appContainerURL.appendingPathComponent("Logs").path
            
            // Create Logs directory if it doesn't exist
            if !FileManager.default.fileExists(atPath: logDirPath) {
                do {
                    try FileManager.default.createDirectory(atPath: logDirPath, withIntermediateDirectories: true, attributes: nil)
                } catch {
                    // Failed to create logs directory
                }
            }
            
            logURL = URL(fileURLWithPath: logDirPath).appendingPathComponent(logFile)
            
            do {
                if !FileManager.default.fileExists(atPath: logURL!.path) {
                    try "".write(to: logURL!, atomically: true, encoding: .utf8)
                }
                try "test".write(to: logURL!, atomically: true, encoding: .utf8)
                logFileCreated = true
                // Production mode: Log file created
            } catch {
                // Failed to create log file in production mode
                
                // Fallback to Documents folder if Applications folder is not writable
                let documentsPath = NSHomeDirectory() + "/Documents"
                let fallbackURL = URL(fileURLWithPath: documentsPath).appendingPathComponent(logFile)
                
                do {
                    if !FileManager.default.fileExists(atPath: fallbackURL.path) {
                        try "".write(to: fallbackURL, atomically: true, encoding: .utf8)
                    }
                    try "test".write(to: fallbackURL, atomically: true, encoding: .utf8)
                    logURL = fallbackURL
                    logFileCreated = true
                    // Fallback: Log file created in Documents folder
                } catch {
                    // Failed to create log file in fallback location
                }
            }
        }
        
        // If we couldn't create a log file, use console only
        if !logFileCreated {
            // Could not create log file. Using console logging only.
            logFileHandle = nil
        } else {
            // Open file handle for writing
            do {
                logFileHandle = try FileHandle(forWritingTo: logURL!)
                logFileHandle?.seekToEndOfFile()
                // Log file handle opened successfully
            } catch {
                // Could not open log file handle. Using console logging only.
                logFileHandle = nil
            }
        }
        
        // Always log startup information
        let username = storage.string(forKey: "username") ?? NSUserName()
        let startupInfo = """
        === MonitorClient Started ===
        Version: \(APP_VERSION)
        Mode: \(isDevelopment ? "Development" : "Production")
        Username: \(username)
        Mac Address: \(macAddress)
        Server IP: \(storage.string(forKey: "server-ip") ?? "unknown")
        Log file location: \(logURL?.path ?? "console only")
        Current directory: \(FileManager.default.currentDirectoryPath)
        Home directory: \(NSHomeDirectory())
        ==========================================
        
        """
        
        // Write to log file if available
        if let data = startupInfo.data(using: .utf8) {
            logFileHandle?.write(data)
            logFileHandle?.synchronizeFile()
        }
        
        // Log the log file location to console for easy access
        print("MonitorClient log file: \(logURL?.path ?? "console only")")
    }
    
    func logMessage(_ message: String, level: LogLevel = .info) {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss.SSS"
        formatter.locale = Locale(identifier: "en_US_POSIX")
        let timestamp = formatter.string(from: Date())
        let logEntry = "[\(timestamp)] [\(level.rawValue)] \(message)\n"
        
        if let data = logEntry.data(using: .utf8) {
            logFileHandle?.write(data)
            logFileHandle?.synchronizeFile()
        }
    }
    
    private func flushLogBuffer() {
        guard !logBuffer.isEmpty else { return }
        
        let combinedLogs = logBuffer.joined()
        if let data = combinedLogs.data(using: .utf8) {
            logFileHandle?.write(data)
            logFileHandle?.synchronizeFile()
        }
        logBuffer.removeAll()
    }
    
    enum LogLevel: String {
        case debug = "DEBUG"
        case info = "INFO"
        case warning = "WARN"
        case error = "ERROR"
        
        var osLogType: OSLogType {
            switch self {
            case .debug: return .debug
            case .info: return .info
            case .warning: return .default
            case .error: return .error
            }
        }
    }
    
    private func logMonitoringEvent(_ event: String, details: String? = nil) {
        // INFO logging disabled - only warnings and errors are logged
    }
    
    private func logError(_ error: String, context: String? = nil) {
        let message = context != nil ? "[\(context!)] \(error)" : error
        logMessage(message, level: .error)
    }
    
    private func logSuccess(_ action: String, details: String? = nil) {
        // INFO logging disabled - only warnings and errors are logged
    }

    func applicationDidFinishLaunching(_ aNotification: Notification) {
        // Insert code here to initialize your application
        
        // App startup - logging disabled for performance
        
        do {
            // Since this is a background app (LSUIElement), ensure it doesn't quit when all windows are closed
            NSApp.setActivationPolicy(.accessory)
            // Set activation policy to accessory
            
            // Prevent any windows from being created
            NSApp.windows.forEach { window in
                window.close()
            }
            // Closed any existing windows
        } catch {
            // Error during app initialization
        }
        
        storage = UserDefaults.init(suiteName: "alice.monitors")

        // Load settings from file
        loadSettings()
        
        // Fetch configuration from API
        fetchConfigurationFromAPI()

        // Configure server IP if not already set
        if storage.string(forKey: "server-ip") == nil {
            storage.set("192.168.1.45", forKey: "server-ip")
            storage.set("8924", forKey: "server-port")
            // logMessage("Server IP configured: 192.168.1.45:8924", level: .info)
        } else {
            // logMessage("Server IP already configured: \(storage.string(forKey: "server-ip") ?? "unknown")", level: .info)
        }
        
        // Check permissions based on app mode and previous state
        checkPermissionsOnStartup()

        APP_VERSION = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.5"

        keyLogs = []
        
        // Get MacAddress
        let address = getMacAddress()
        if !address.isEmpty {
            macAddress = address
            // logMessage("Mac Address obtained: \(address)", level: .info)
        } else {
            logMessage("Warning: Could not get MacAddress", level: .warning)
            macAddress = ""
        }
        
        // Get Client IP Address (once at startup)
        clientIPAddress = getClientIPAddress()
        // logMessage("Client IP Address obtained: \(clientIPAddress)", level: .info)

        // Setup logging after basic initialization
        setupLogging()

        // Check if MonitorChecker is running and start it if not
        logMonitoringEvent("Checking MonitorChecker")
        checkMonitorChecker()
        
        // Start keyboard monitoring
        logMonitoringEvent("Starting keyboard monitoring")
        
        // Check if app has proper entitlements
        if let bundleIdentifier = Bundle.main.bundleIdentifier {
            logMessage("App bundle identifier: \(bundleIdentifier)", level: .debug)
        }
        
        startKeyboardMonitoring();
        
        // Setup event tap invalidation monitoring
        logMonitoringEvent("Setting up event tap monitoring")
        setupEventTapInvalidationMonitoring()
        
        // Single timer for all tasks (optimized interval)
        timer = Timer.scheduledTimer(timeInterval: 5.0, target: self, selector: #selector(checkAllTasks), userInfo: nil, repeats: true)
        logSuccess("Main monitoring timer started")
        
        let center = NSWorkspace.shared.notificationCenter
        center.addObserver(self, selector: #selector(sessionDidBecomeActive), name: NSWorkspace.sessionDidBecomeActiveNotification, object: nil)
        center.addObserver(self, selector: #selector(sessionDidResignActive), name: NSWorkspace.sessionDidResignActiveNotification, object: nil)
        activeRunning = true
        
        // Add observer for application termination
        NSWorkspace.shared.notificationCenter.addObserver(
            self,
            selector: #selector(applicationDidTerminate(_:)),
            name: NSWorkspace.didTerminateApplicationNotification,
            object: nil
        )
        
        usbDeviceLogs = []
        logMonitoringEvent("Setting up USB monitoring")
        setupUSBMonitoring()
        
        // Send all monitoring requests on startup for quick verification
        
        // Debug accessibility status on startup
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            self.debugAccessibilityStatus()
        }
        
        // Check app entitlements on startup
        DispatchQueue.main.asyncAfter(deadline: .now() + 3.0) {
            self.checkAppEntitlements()
        }
        
        // Set up notification delegate
        configureNotifications()
        
        // Setup status bar menu
        do {
            setupStatusBarMenu()
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.5) {
                self.restoreRememberedSession()
            }
            // Status bar menu setup completed
        } catch {
            // Error setting up status bar menu
        }
        
        // Setup log flush timer (flush logs every 30 seconds)
        logFlushTimer = Timer.scheduledTimer(timeInterval: 30.0, target: self, selector: #selector(flushLogsPeriodically), userInfo: nil, repeats: true)
        
        logSuccess("MonitorClient initialization complete")
        // MonitorClient startup complete
        
        // Show a test notification to verify app is running
        showClientNotification("MonitorClient Started", "App is running in the menu bar.")
        
        // Check permissions and log status
        checkAndLogPermissions()
    }
    
    @objc func sessionDidBecomeActive(notification: Notification) {
        // logMessage("User switched back to this session", level: .info)
        activeRunning = true
    }

    @objc func sessionDidResignActive(notification: Notification) {
        // logMessage("User switched away from this session", level: .info)
        activeRunning = false
    }
    
    @objc func flushLogsPeriodically() {
        flushLogBuffer()
    }
    
    // Perform all monitoring tests on startup for quick verification
    
    // Check if system is idle before performing heavy operations
    private func shouldPerformHeavyOperation() -> Bool {
        // Check for any recent user activity (keyboard, mouse, etc.)
        let lastKeyActivity = CGEventSource.secondsSinceLastEventType(.combinedSessionState, eventType: .keyDown)
        let lastMouseActivity = CGEventSource.secondsSinceLastEventType(.combinedSessionState, eventType: .leftMouseDown)
        
        // Consider system active if there was any input in the last 5 minutes
        return lastKeyActivity < 300 || lastMouseActivity < 300
    }
    
    // Check if screen recording permission is granted
    private func isScreenRecordingEnabled() -> Bool {
        if #available(macOS 10.15, *) {
            return CGPreflightScreenCaptureAccess()
            } else {
            // Fallback for older macOS versions
            return true
        }
    }
    
    // Check permissions on startup based on app mode and previous state
    private func checkPermissionsOnStartup() {
        // Determine if we're in development or production mode
        let isDevelopment = Bundle.main.bundlePath.contains("DerivedData") || Bundle.main.bundlePath.contains("Debug")
        // logMessage("App mode: \(isDevelopment ? "DEVELOPMENT" : "PRODUCTION")", level: .info)
        
        // Check accessibility permission
        let accessibilityWasGranted = storage.bool(forKey: "accessibility-was-granted")
        let accessibilityCurrentGranted = AXIsProcessTrusted()
        
        if accessibilityWasGranted && !accessibilityCurrentGranted {
            // Permission was revoked, reset the prompt flag so we can show instructions again
            storage.removeObject(forKey: "accessibility-prompt-shown")
            logMessage("Accessibility permission was revoked, resetting prompt flag", level: .warning)
        } else if !accessibilityWasGranted && accessibilityCurrentGranted {
            // Permission was newly granted, update the flag
            storage.set(true, forKey: "accessibility-was-granted")
            // logMessage("Accessibility permission newly granted", level: .info)
        }
        
        // logMessage("Accessibility permission status: \(accessibilityCurrentGranted ? "GRANTED" : "DENIED") (was previously granted: \(accessibilityWasGranted))", level: .info)
        
        // Check screen recording permission
        if #available(macOS 10.15, *) {
            let screenRecordingWasGranted = storage.bool(forKey: "screen-recording-was-granted")
            let screenRecordingCurrentGranted = CGPreflightScreenCaptureAccess()
            
            if screenRecordingWasGranted && !screenRecordingCurrentGranted {
                // Permission was revoked, reset the prompt flag so we can show instructions again
                storage.removeObject(forKey: "screen-recording-prompt-shown")
                logMessage("Screen recording permission was revoked, resetting prompt flag", level: .warning)
            } else if !screenRecordingWasGranted && screenRecordingCurrentGranted {
                // Permission was newly granted, update the flag
                storage.set(true, forKey: "screen-recording-was-granted")
                // logMessage("Screen recording permission newly granted", level: .info)
            }
            
            // logMessage("Screen recording permission status: \(screenRecordingCurrentGranted ? "GRANTED" : "DENIED") (was previously granted: \(screenRecordingWasGranted))", level: .info)
            
            // Determine if we should request permissions
            let shouldRequestAccessibility = !accessibilityCurrentGranted && shouldRequestPermission(
                wasPreviouslyGranted: accessibilityWasGranted,
                isDevelopment: isDevelopment,
                permissionType: "accessibility"
            )
            
            let shouldRequestScreenRecording = !screenRecordingCurrentGranted && shouldRequestPermission(
                wasPreviouslyGranted: screenRecordingWasGranted,
                isDevelopment: isDevelopment,
                permissionType: "screen-recording"
            )
            
            // Request permissions if needed
            if shouldRequestAccessibility {
                // logMessage("Requesting accessibility permission on startup", level: .info)
                DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) {
                    self.requestAccessibilityPermission()
                }
            }
            
            if shouldRequestScreenRecording {
                // logMessage("Requesting screen recording permission on startup", level: .info)
                DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
                    self.requestScreenRecordingPermission()
                }
            }
        }
    }
    
    // Determine if we should request a permission based on app mode and previous state
    private func shouldRequestPermission(wasPreviouslyGranted: Bool, isDevelopment: Bool, permissionType: String) -> Bool {
        let promptShownKey = "\(permissionType)-prompt-shown"
        let promptShown = storage.bool(forKey: promptShownKey)
        
        if isDevelopment {
            // Development mode: be more aggressive about requesting permissions
            // since the app path changes frequently and permissions might not persist
            logMessage("Development mode: checking \(permissionType) permission (was granted: \(wasPreviouslyGranted), prompt shown: \(promptShown))", level: .debug)
            
            // In dev mode, request if:
            // 1. Never granted before, OR
            // 2. Was granted but now revoked, OR
            // 3. Never prompted before
            return !wasPreviouslyGranted || promptShown == false
        } else {
            // Production mode: be more conservative about requesting permissions
            // to avoid spamming the user
            logMessage("Production mode: checking \(permissionType) permission (was granted: \(wasPreviouslyGranted), prompt shown: \(promptShown))", level: .debug)
            
            // In prod mode, request if:
            // 1. Was granted but now revoked, OR
            // 2. Never prompted before
            return (wasPreviouslyGranted && promptShown == false) || (!wasPreviouslyGranted && promptShown == false)
        }
    }
    
    // Request screen recording permission
    private func requestScreenRecordingPermission() {
        if #available(macOS 10.15, *) {
            // Check if we already have permission
            if CGPreflightScreenCaptureAccess() {
                // logMessage("Screen recording permission already granted", level: .info)
                storage.set(true, forKey: "screen-recording-was-granted")
                return
            }
            
            // Check if permission was previously granted
            let wasPreviouslyGranted = storage.bool(forKey: "screen-recording-was-granted")
            
            // Only prompt once per session to avoid spam, unless permission was revoked
            let promptKey = "screen-recording-prompt-shown"
            let promptShown = storage.bool(forKey: promptKey)
            logMessage("Screen recording permission check - wasPreviouslyGranted: \(wasPreviouslyGranted), promptShown: \(promptShown)", level: .debug)
            
            if promptShown && !wasPreviouslyGranted {
                // logMessage("Screen recording prompt already shown this session and permission was never granted, but will show again for debugging", level: .info)
                // Don't return - continue to show the permission request
            }
            
            // logMessage("Requesting screen recording permission...", level: .info)
            
            // Method 1: Try CGRequestScreenCaptureAccess() first
            // logMessage("Calling CGRequestScreenCaptureAccess() to trigger system dialog", level: .info)
            CGRequestScreenCaptureAccess()
            
            // Method 2: Also try to capture screen as backup - this will trigger the system dialog if permission not granted
            // logMessage("Attempting to capture screen to trigger permission dialog", level: .info)
            let _ = CGWindowListCreateImage(CGRect.null, .optionOnScreenOnly, kCGNullWindowID, .bestResolution)
            
            // Show detailed notification based on previous state
            let notification = UNMutableNotificationContent()
            notification.title = "MonitorClient - Screen Recording Permission Required"
            
            if wasPreviouslyGranted {
                // App was previously granted but permission was revoked
                notification.body = """
                Screen recording permission was revoked. To restore monitoring:
                
                1. Open System Preferences > Security & Privacy > Privacy > Screen Recording
                2. Find "MonitorClient" in the list
                3. Click the "-" button to remove it
                4. Click the "+" button and add MonitorClient again
                5. Restart the MonitorClient app
                
                This will restore screenshot monitoring functionality.
                """
                logMessage("Screen recording permission was previously granted but has been revoked", level: .warning)
            } else {
                // First time requesting permission
                notification.body = """
                MonitorClient needs screen recording permission to capture screenshots.
                
                To grant permission:
                1. Open System Preferences > Security & Privacy > Privacy > Screen Recording
                2. Click the lock icon and enter your password
                3. Click the "+" button and add MonitorClient
                4. Check the box next to MonitorClient
                5. Restart the MonitorClient app
                
                Without this permission, screenshot monitoring will not work.
                """
                // logMessage("First time requesting screen recording permission", level: .info)
            }
            
            notification.sound = .default
            self.deliverNotification(notification)
            
            // Check if permission was granted after the screen capture attempt
            // Use a timer to check multiple times since the dialog might take time to appear
            var checkCount = 0
            let maxChecks = 10
            let checkInterval = 1.0
            
            Timer.scheduledTimer(withTimeInterval: checkInterval, repeats: true) { timer in
                checkCount += 1
                
                if CGPreflightScreenCaptureAccess() {
                    // self.logMessage("Screen recording permission granted after \(checkCount) checks", level: .info)
                    self.storage.set(true, forKey: "screen-recording-was-granted")
                    // Clear the prompt flag when permission is granted so we can show it again if revoked
                    self.storage.removeObject(forKey: promptKey)
                    timer.invalidate()
                } else if checkCount >= maxChecks {
                    self.logMessage("Screen recording permission still denied after \(maxChecks) checks - detailed instructions sent via notification", level: .warning)
                    // Mark that we've shown the prompt (only if denied)
                    self.storage.set(true, forKey: promptKey)
                    timer.invalidate()
                } else {
                    self.logMessage("Screen recording permission check \(checkCount)/\(maxChecks) - still waiting...", level: .debug)
                }
            }
            
            // logMessage("Screen recording permission request sent", level: .info)
        } else {
            // logMessage("Screen recording permission not required on this macOS version", level: .info)
        }
    }

    @objc func checkAllTasks() {
        guard selectedMonitorId>0 && !clientToken.isEmpty else { return }
        let currentDate = Date()
        
        // Check accessibility permission periodically
        checkAccessibilityPermissionPeriodically()
        
        // Check screen recording permission periodically
        checkScreenRecordingPermissionPeriodically()
        
        // Add event tap validation check to main timer loop
        checkAndReestablishEventTapThrottled()
                
        // Check if it's time for screenshots (with activity check)
        if currentDate.timeIntervalSince(lastScreenshotCheck) >= TimeInterval(TIME_INTERVAL) {
            if shouldPerformHeavyOperation() {
                logMonitoringEvent("Screenshot monitoring triggered", details: "Interval: \(TIME_INTERVAL)s")
                let randomDelay = Double.random(in: 0...Double(TIME_INTERVAL))
                perform(#selector(TakeScreenShotsAndPost), with: nil, afterDelay: randomDelay)
            } else {
                logMessage("Skipping screenshot - system appears idle", level: .debug)
            }
            lastScreenshotCheck = currentDate
        }
        
        // Check if it's time for tic event
        if currentDate.timeIntervalSince(lastTicCheck) >= TimeInterval(TIC_INTERVAL) {
            logMonitoringEvent("Tic event monitoring triggered", details: "Interval: \(TIC_INTERVAL)s")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendTicEvent()
                } catch {
                    self.logError("Error sending tic event: \(error)", context: "TicEvent")
                }
            }
            lastTicCheck = currentDate
        }

        // Check if it's time for browser history
        if currentDate.timeIntervalSince(lastHistoryCheck) >= TimeInterval(HISTORY_INTERVAL) {
                logMonitoringEvent("Browser history monitoring triggered", details: "Interval: \(HISTORY_INTERVAL)s")
                DispatchQueue.global(qos: .background).async {
                    do {
                        try self.sendBrowserHistories()
                    } catch {
                        self.logError("Error sending browser histories: \(error)", context: "BrowserHistory")
                    }
                }
                lastHistoryCheck = currentDate
        }

        // Check if it's time for key log
        if currentDate.timeIntervalSince(lastKeyCheck) >= TimeInterval(KEY_INTERVAL) {
            logMonitoringEvent("Key log monitoring triggered", details: "Interval: \(KEY_INTERVAL)s, Keys collected: \(keyLogs.count)")
            DispatchQueue.global(qos: .background).async {
                self.sendKeyLogs()
            }
            lastKeyCheck = currentDate
        }
        
        // Check if it's time for USB log (using configurable interval)
        if currentDate.timeIntervalSince(lastUSBCheck) >= TimeInterval(USB_INTERVAL) {
            logMonitoringEvent("USB log monitoring triggered", details: "Interval: \(USB_INTERVAL)s, USB events collected: \(usbDeviceLogs.count)")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendUSBLogs()
                } catch {
                    self.logError("Error sending usb logs: \(error)", context: "USBLog")
                }
            }
            lastUSBCheck = currentDate
        }
    }

    /// Throttled event tap validation to prevent app from getting stuck
    private func checkAndReestablishEventTapThrottled() {
        let currentDate = Date()
        
        // Check 1: Regular interval check (every 3 seconds)
        let shouldCheckInterval = currentDate.timeIntervalSince(lastEventTapCheck) >= eventTapCheckInterval
        
        // Check 2: Key capture gap detection (if no keys for 10 seconds)
        let keyCaptureGap = currentDate.timeIntervalSince(lastKeyCaptureTime)
        let shouldCheckKeyGap = keyCaptureGap >= maxKeyCaptureGap
        
        // Only proceed if one of the conditions is met
        guard shouldCheckInterval || shouldCheckKeyGap else {
            return
        }
        
        // Prevent multiple simultaneous re-establishment attempts
        guard !isReestablishingEventTap else {
            return
        }
        
        // Additional check: only re-establish if we have accessibility permission
        guard isInputMonitoringEnabled() else {
            // Don't log this message repeatedly to avoid spam
            let lastSkipLog = storage.double(forKey: "last-skip-log-time")
            let currentTime = Date().timeIntervalSince1970
            if currentTime - lastSkipLog > 30 { // Only log every 30 seconds
                logMessage("Skipping event tap check - no accessibility permission", level: .debug)
                storage.set(currentTime, forKey: "last-skip-log-time")
            }
            return
        }
        
        lastEventTapCheck = currentDate
        
        // Log the reason for checking (but less frequently)
        if shouldCheckKeyGap && keyCaptureGap.truncatingRemainder(dividingBy: 30) < 1 {
            logMessage("No key capture detected for \(Int(keyCaptureGap))s, checking event tap...", level: .debug)
        }
        
        // Perform the check asynchronously to avoid blocking the main thread
        DispatchQueue.global(qos: .utility).async { [weak self] in
            self?.performEventTapValidation()
        }
    }
    
    /// Perform actual event tap validation (called on background queue)
    private func performEventTapValidation() {
        // Quick check first - if event tap is nil, we need to re-establish
        guard let tap = eventTap else {
            // logMessage("Event tap is nil, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Check if tap is enabled (this is a lightweight operation)
        let isEnabled = CGEvent.tapIsEnabled(tap: tap)
        if !isEnabled {
            // logMessage("Event tap became disabled, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Additional validation: check if mach port is still valid
        let portValid = CFMachPortIsValid(tap)
        if !portValid {
            // logMessage("Event tap mach port is invalid, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Event tap is valid and working
        // logMessage("Event tap validation passed", level: .debug)
    }
    
    /// Re-establish event tap asynchronously with timeout
    private func reestablishEventTapAsync() {
        isReestablishingEventTap = true
        
        // Set a timeout to prevent infinite hanging
        let timeoutWorkItem = DispatchWorkItem { [weak self] in
            self?.isReestablishingEventTap = false
            self?.logMessage("Event tap re-establishment timed out", level: .warning)
        }
        
        DispatchQueue.main.asyncAfter(deadline: .now() + 10.0, execute: timeoutWorkItem)
        
        // Perform re-establishment on main queue (required for UI operations)
        DispatchQueue.main.async { [weak self] in
            guard let self = self else { return }
            
            defer {
                self.isReestablishingEventTap = false
                timeoutWorkItem.cancel()
            }
            
            // Check permission before attempting to re-establish
            guard self.isInputMonitoringEnabled() else {
                self.logMessage("Cannot re-establish event tap - no accessibility permission", level: .warning)
                return
            }
            
            do {
                self.setupKeyboardMonitoring()
                // self.logMessage("Event tap re-establishment completed successfully", level: .info)
            } catch {
                self.logMessage("Event tap re-establishment failed: \(error)", level: .error)
            }
        }
    }

    func buildEndpoint(_ mode: Bool) -> String? {
        // Get server IP and port
        let serverIP = storage.string(forKey: "server-ip") ?? ""
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        
        // Handle both formats: full address (IP:PORT) or separate IP and port
        let finalIP: String
        let finalPort: String
        
        if serverIP.contains(":") {
            // Full address format (IP:PORT)
            let components = serverIP.split(separator: ":")
            if components.count >= 2 {
                finalIP = String(components[0])
                finalPort = String(components[1])
            } else {
                finalIP = serverIP
                finalPort = serverPort
            }
        } else {
            // Separate IP and port
            finalIP = serverIP
            finalPort = serverPort
        }
        
        if !finalIP.isEmpty {
            let scheme = finalPort == "8924" ? "http" : "https"
        let endpoint = "\(scheme)://\(finalIP):\(finalPort)" + (mode ? API_ROUTE : TIC_ROUTE)
            logMessage("Built endpoint: \(endpoint)", level: .debug)
            return endpoint
        } else {
            logError("Server IP not configured", context: "Endpoint")
            return nil
        }
    }
    
    private func createTemporaryCopy(_ path: String, browser: String) throws -> String {
        let originalFileName = (path as NSString).lastPathComponent
        let randomString = generateRandomString(length: 8)
        let fileName = "\(browser)_\(randomString)_\(originalFileName)"
        let temporaryPath = NSTemporaryDirectory() + fileName
        
        // Check if source file exists and is accessible
        guard FileManager.default.fileExists(atPath: path) else {
            throw NSError(domain: "FileError", code: 1, userInfo: [NSLocalizedDescriptionKey: "Source file does not exist: \(path)"])
        }
        
        // Check if file is readable
        guard FileManager.default.isReadableFile(atPath: path) else {
            throw NSError(domain: "FileError", code: 2, userInfo: [NSLocalizedDescriptionKey: "Source file is not readable: \(path)"])
        }
        
        // Try to get file attributes to check if it's being modified
        do {
            let attributes = try FileManager.default.attributesOfItem(atPath: path)
            if let modificationDate = attributes[.modificationDate] as? Date {
                let timeSinceModification = Date().timeIntervalSince(modificationDate)
                // If file was modified in the last 5 seconds, it might be actively being written to
                if timeSinceModification < 5.0 {
                    debugPrint("Warning: File \(path) was recently modified (\(timeSinceModification)s ago), may be actively in use")
                }
            }
        } catch {
            debugPrint("Warning: Could not get file attributes for \(path): \(error)")
        }
        
        // Copy the database with retry mechanism
        var lastError: Error?
        let maxRetries = 3
        let retryDelay: TimeInterval = 1.0
        
        for attempt in 1...maxRetries {
            do {
                try FileManager.default.copyItem(atPath: path, toPath: temporaryPath)
                // Success - break out of retry loop
                break
            } catch {
                lastError = error
                debugPrint("Attempt \(attempt)/\(maxRetries) failed to copy \(path): \(error.localizedDescription)")
                
                if attempt < maxRetries {
                    debugPrint("Waiting \(retryDelay)s before retry...")
                    Thread.sleep(forTimeInterval: retryDelay)
                }
            }
        }
        
        // If all retries failed, throw the last error
        if let error = lastError {
            throw NSError(domain: "FileError", code: 3, userInfo: [NSLocalizedDescriptionKey: "Failed to copy file after \(maxRetries) attempts: \(error.localizedDescription)"])
        }
        
        return temporaryPath
    }

    private func processChromeBasedProfiles(profilesPath: String, browser: String, checkedVariable: inout Int64?) throws -> [BrowserHistoryLog] {
        let fileMan = FileManager()
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        var highestTimestamp: Int64? = checkedVariable
        
        // logMessage("=== Starting \(browser) history collection ===", level: .info)
        logMessage("Profiles path: \(profilesPath)", level: .debug)
        logMessage("Current checkedVariable (highestTimestamp): \(checkedVariable?.description ?? "nil")", level: .debug)
        
        if fileMan.fileExists(atPath: profilesPath) {
            // logMessage("\(browser) profiles folder found at: \(profilesPath)", level: .info)
            
            // Only check top-level directories that are actual profiles
            // Chrome profiles are: "Default", "Profile 1", "Profile 2", etc.
            // Skip: "System Profile", "Guest Profile", hidden directories, and subdirectories
            do {
                let contents = try fileMan.contentsOfDirectory(atPath: profilesPath)
                for profileName in contents {
                    // Skip system profiles and hidden directories
                    if profileName == "System Profile" || profileName == "Guest Profile" || profileName.hasPrefix(".") {
                        continue
                    }
                    
                    // Only process if it's a directory (not a file)
                    var isDirectory: ObjCBool = false
                    let profilePath = "\(profilesPath)\(profileName)"
                    if !fileMan.fileExists(atPath: profilePath, isDirectory: &isDirectory) || !isDirectory.boolValue {
                        continue
                    }
                    
                    // Check if this directory contains a History file (indicating it's a real profile)
                    let historyPath = "\(profilePath)/History"
                    logMessage("Checking profile: \(profileName), history path: \(historyPath)", level: .debug)
                    if fileMan.fileExists(atPath: historyPath) {
                        logMessage("History file found for profile: \(profileName)", level: .debug)
                        let temporaryPath:String = try createTemporaryCopy(historyPath, browser: browser)
                        logMessage("Created temporary copy: \(temporaryPath)", level: .debug)
                        
                        var db : OpaquePointer?
                        let dbURL = URL(fileURLWithPath: temporaryPath)
                        
                        // Check if file is a valid SQLite database
                        logMessage("Opening SQLite database: \(dbURL.path)", level: .debug)
                        let openResult = sqlite3_open(dbURL.path, &db)
                        if openResult != SQLITE_OK {
                            let errorMessage = String(cString: sqlite3_errmsg(db))
                            logError("Could not open \(browser) History file \(historyPath) - SQLite error: \(errorMessage)", context: "\(browser)History")
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        logMessage("SQLite database opened successfully", level: .debug)
                        
                        // Verify database is not corrupted with better error handling
                        var isCorrupted = false
                        var errorMessage: UnsafeMutablePointer<Int8>?
                        let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
                        
                        if integrityResult != SQLITE_OK {
                            isCorrupted = true
                            if let errorMsg = errorMessage {
                                debugPrint("[-] \(browser) History \(historyPath) integrity check failed: \(String(cString: errorMsg))")
                                sqlite3_free(errorMessage)
                            }
                        }
                        
                        if isCorrupted {
                            debugPrint("[-] \(browser) History \(historyPath) is corrupted")
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        
                        // Additional safety check - try to read database header
                        var headerCheck: OpaquePointer?
                        let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
                        if headerResult != SQLITE_OK {
                            debugPrint("[-] \(browser) History \(historyPath) appears to be locked or corrupted - cannot read table structure")
                            sqlite3_finalize(headerCheck)
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        sqlite3_finalize(headerCheck)
                        
                        // Initialize timestamp if needed - collect from last 7 days on first run
                        if highestTimestamp == nil {
                            let defaultTime = Date().timeIntervalSince1970 - (7 * 24 * 3600) // 7 days ago
                            let currentDate = Date(timeIntervalSince1970: defaultTime)
                            highestTimestamp = (Int64(currentDate.timeIntervalSince1970) + 11644473600) * 1000000
                            // logMessage("Initialized timestamp from 7 days ago: \(highestTimestamp ?? 0)", level: .info)
                        } else {
                            logMessage("Using existing timestamp: \(highestTimestamp ?? 0)", level: .debug)
                        }
                        
                        // Use >= instead of > to include entries at the exact timestamp boundary, and add LIMIT to prevent excessive data
                        let queryString = "select datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time from urls, visits where visits.url = urls.id and visit_time >= \(highestTimestamp ?? 0) order by visit_time LIMIT 10000;"
                        logMessage("Executing query with timestamp >= \(highestTimestamp ?? 0)", level: .debug)
                        
                        var queryStatement: OpaquePointer? = nil
                        var entryCount = 0
                        
                        if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK {
                            logMessage("Query prepared successfully", level: .debug)
                            while sqlite3_step(queryStatement) == SQLITE_ROW {
                                entryCount += 1
                                let col1 = sqlite3_column_text(queryStatement, 0)
                                if col1 != nil {
                                    visitDate = String(cString: col1!)
                                }
                                
                                let col2 = sqlite3_column_text(queryStatement, 1)
                                if col2 != nil {
                                    histURL = String(cString: col2!)
                                }
                                
                                let visitTime = sqlite3_column_int64(queryStatement, 2)
                                if (highestTimestamp! < visitTime) {
                                    highestTimestamp = visitTime
                                }
                                
                                // Log first few entries for debugging
                                if entryCount <= 3 {
                                    logMessage("Entry \(entryCount): URL=\(histURL), visit_time=\(visitTime), date=\(visitDate)", level: .debug)
                                }
                                
                                browseHist.append(BrowserHistoryLog(
                                    browser: browser,
                                    url: histURL,
                                    title: histURL, // Use URL as title for now
                                    last_visit: visitTime,
                                    date: getCurrentDateTimeString()
                                ))
                            }
                            
                            sqlite3_finalize(queryStatement)
                            // logMessage("Query completed: found \(entryCount) entries from profile \(profileName)", level: .info)
                            logMessage("Updated highestTimestamp to: \(highestTimestamp ?? 0)", level: .debug)
                        } else {
                            let errorMsg = String(cString: sqlite3_errmsg(db))
                            logError("Could not prepare query for \(browser) History - SQLite error: \(errorMsg)", context: "\(browser)History")
                        }
                        
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        logMessage("Closed database and removed temporary file", level: .debug)
                    }
                }
            } catch {
                logError("Error reading \(browser) profiles directory: \(error)", context: "\(browser)History")
            }
        } else {
            logMessage("\(browser) profiles folder not found at: \(profilesPath)", level: .warning)
        }
        
        // Update the original checkedVariable with the highest timestamp found across all profiles
        checkedVariable = highestTimestamp
        // logMessage("=== Completed \(browser) history collection: \(browseHist.count) total entries ===", level: .info)
        logMessage("Updated checkedVariable to: \(checkedVariable?.description ?? "nil")", level: .debug)
        return browseHist
    }

    func getSafariHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        
        var isDir = ObjCBool(true)
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        
        // Safari history check
        if fileMan.fileExists(atPath: "/Users/\(username)/Library/Safari/History.db", isDirectory: &isDir) {
            let temporaryPath:String = try createTemporaryCopy("/Users/\(username)/Library/Safari/History.db", browser: "Safari")
            logMessage("Created temporary copy: \(temporaryPath)", level: .debug)
            
            var db : OpaquePointer?
            let dbURL = URL(fileURLWithPath: temporaryPath)
            
            // Check if file is a valid SQLite database with better error handling
            logMessage("Opening SQLite database: \(dbURL.path)", level: .debug)
            let openResult = sqlite3_open(dbURL.path, &db)
            if openResult != SQLITE_OK {
                let errorMessage = String(cString: sqlite3_errmsg(db))
                logError("Could not open Safari History.db - SQLite error: \(errorMessage)", context: "SafariHistory")
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            logMessage("SQLite database opened successfully", level: .debug)
            
            // Verify database is not corrupted with better error handling
            var isCorrupted = false
            var errorMessage: UnsafeMutablePointer<Int8>?
            let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
            
            if integrityResult != SQLITE_OK {
                isCorrupted = true
                if let errorMsg = errorMessage {
                    debugPrint("[-] Safari History.db integrity check failed: \(String(cString: errorMsg))")
                    sqlite3_free(errorMessage)
                }
            }
            
            if isCorrupted {
                debugPrint("[-] Safari History.db is corrupted")
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            
            // Additional safety check - try to read database header
            var headerCheck: OpaquePointer?
            let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
            if headerResult != SQLITE_OK {
                debugPrint("[-] Safari History.db appears to be locked or corrupted - cannot read table structure")
                sqlite3_finalize(headerCheck)
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            sqlite3_finalize(headerCheck)
            
            if safariChecked == nil{
                // Collect from last 7 days on first run
                let defaultTime = Date().timeIntervalSince1970 - (7 * 24 * 3600) // 7 days ago
                let currentDate = Date(timeIntervalSince1970: defaultTime)
                safariChecked = currentDate.timeIntervalSince1970 - 978307200
                // logMessage("Initialized safariChecked from 7 days ago: \(safariChecked ?? 0)", level: .info)
            } else {
                logMessage("Using existing safariChecked: \(safariChecked ?? 0)", level: .debug)
            }
            // Convert timestamp to VLAT timestring - use >= and add LIMIT to prevent excessive data
            let queryString = "select datetime(history_visits.visit_time + 978307200, 'unixepoch', '+10:00') as last_visited, history_items.url, history_visits.visit_time from history_visits, history_items where history_visits.history_item=history_items.id and history_visits.visit_time >= \(safariChecked ?? 0) order by history_visits.visit_time LIMIT 10000;"
            logMessage("Executing query with timestamp >= \(safariChecked ?? 0)", level: .debug)
            var queryStatement: OpaquePointer? = nil
            var entryCount = 0

            if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK{
                logMessage("Query prepared successfully", level: .debug)
                while sqlite3_step(queryStatement) == SQLITE_ROW{
                    entryCount += 1
                    let col1 = sqlite3_column_text(queryStatement, 0)
                    if col1 != nil{
                        visitDate = String(cString: col1!)
                    }
                    let col2 = sqlite3_column_text(queryStatement, 1)
                    if col2 != nil{
                        histURL = String(cString: col2!)
                    }

                    let visitTime = sqlite3_column_double(queryStatement, 2)
                    if (safariChecked! < visitTime) {
                        safariChecked = visitTime
                    }
                    
                    // Log first few entries for debugging
                    if entryCount <= 3 {
                        logMessage("Entry \(entryCount): URL=\(histURL), visit_time=\(visitTime), date=\(visitDate)", level: .debug)
                    }

                    browseHist.append(BrowserHistoryLog(
                        browser: "Safari",
                        url: histURL,
                        title: histURL, // Use URL as title for now
                        last_visit: Int64(visitTime),
                        date: getCurrentDateTimeString()
                    ))
                }
                sqlite3_finalize(queryStatement)
                // logMessage("Query completed: found \(entryCount) entries", level: .info)
                logMessage("Updated safariChecked to: \(safariChecked ?? 0)", level: .debug)
            } else {
                let errorMsg = String(cString: sqlite3_errmsg(db))
                logError("Could not prepare query for Safari History - SQLite error: \(errorMsg)", context: "SafariHistory")
            }
            
            sqlite3_close(db)
            try? FileManager.default.removeItem(atPath: temporaryPath)
            logMessage("Closed database and removed temporary file", level: .debug)
        }
        else {
            logMessage("Safari History.db database not found at: /Users/\(username)/Library/Safari/History.db", level: .warning)
        }
        
        // logMessage("=== Completed Safari history collection: \(browseHist.count) total entries ===", level: .info)
        return browseHist
    }

    func getChromeHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let chromeProfilesPath = "/Users/\(username)/Library/Application Support/Google/Chrome/"
        
        if !fileMan.fileExists(atPath: chromeProfilesPath) {
            logMessage("Chrome profiles folder not found at: \(chromeProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: chromeProfilesPath, browser: "Chrome", checkedVariable: &chromeChecked)
    }

    func getFirefoxHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        
        // logMessage("=== Starting Firefox history collection ===", level: .info)
        logMessage("Current firefoxChecked: \(firefoxChecked?.description ?? "nil")", level: .debug)
        
        let firefoxPath = "/Users/\(username)/Library/Application Support/Firefox/Profiles/"
        // Firefox history check
        if fileMan.fileExists(atPath: firefoxPath){
            // logMessage("Firefox Profiles folder found at: \(firefoxPath)", level: .info)
            let fileEnum = fileMan.enumerator(atPath: "/Users/\(username)/Library/Application Support/Firefox/Profiles/")

            while let each = fileEnum?.nextObject() as? String {
                if each.hasSuffix("places.sqlite") {
                    let placesDBPath = "/Users/\(username)/Library/Application Support/Firefox/Profiles/\(each)"
                    logMessage("Found Firefox profile: \(each)", level: .debug)
                    let temporaryPath:String = try createTemporaryCopy(placesDBPath, browser: "Firefox")
                    logMessage("Created temporary copy: \(temporaryPath)", level: .debug)
                    var db : OpaquePointer?
                    let dbURL = URL(fileURLWithPath: temporaryPath)

                    // Check if file is a valid SQLite database
                    logMessage("Opening SQLite database: \(dbURL.path)", level: .debug)
                    let openResult = sqlite3_open(dbURL.path, &db)
                    if openResult != SQLITE_OK {
                        let errorMessage = String(cString: sqlite3_errmsg(db))
                        logError("Could not open Firefox \(temporaryPath) - SQLite error: \(errorMessage)", context: "FirefoxHistory")
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    logMessage("SQLite database opened successfully", level: .debug)
                    
                    // Verify database is not corrupted with better error handling
                    var isCorrupted = false
                    var errorMessage: UnsafeMutablePointer<Int8>?
                    let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
                    
                    if integrityResult != SQLITE_OK {
                        isCorrupted = true
                        if let errorMsg = errorMessage {
                            debugPrint("[-] Firefox \(temporaryPath) integrity check failed: \(String(cString: errorMsg))")
                            sqlite3_free(errorMessage)
                        }
                    }
                    
                    if isCorrupted {
                        debugPrint("[-] Firefox \(temporaryPath) is corrupted")
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    
                    // Additional safety check - try to read database header
                    var headerCheck: OpaquePointer?
                    let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
                    if headerResult != SQLITE_OK {
                        debugPrint("[-] Firefox \(temporaryPath) appears to be locked or corrupted - cannot read table structure")
                        sqlite3_finalize(headerCheck)
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    sqlite3_finalize(headerCheck)
                    
                    if firefoxChecked == nil{
                        // Collect from last 7 days on first run
                        let defaultTime = Date().timeIntervalSince1970 - (7 * 24 * 3600) // 7 days ago
                        firefoxChecked = Int64(defaultTime * 1000000)
                        // logMessage("Initialized firefoxChecked from 7 days ago: \(firefoxChecked ?? 0)", level: .info)
                    } else {
                        logMessage("Using existing firefoxChecked: \(firefoxChecked ?? 0)", level: .debug)
                    }

                    let queryString = "select datetime(visit_date/1000000, 'unixepoch', '+10:00') as time, url, visit_date FROM moz_places, moz_historyvisits where moz_places.id=moz_historyvisits.place_id and visit_date > \(firefoxChecked ?? 0) order by visit_date;"
                    logMessage("Executing query with visit_date > \(firefoxChecked ?? 0)", level: .debug)

                    var queryStatement: OpaquePointer? = nil
                    var entryCount = 0

                    if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK{
                        logMessage("Query prepared successfully", level: .debug)
                        while sqlite3_step(queryStatement) == SQLITE_ROW{
                            entryCount += 1
                            let col1 = sqlite3_column_text(queryStatement, 0)
                            if col1 != nil{
                                visitDate = String(cString: col1!)
                            }

                            let col2 = sqlite3_column_text(queryStatement, 1)
                            if col2 != nil{
                                histURL = String(cString: col2!)
                            }

                            let visitTime = sqlite3_column_int64(queryStatement, 2)
                            if (firefoxChecked! < visitTime) {
                                firefoxChecked = visitTime
                            }
                            
                            // Log first few entries for debugging
                            if entryCount <= 3 {
                                logMessage("Entry \(entryCount): URL=\(histURL), visit_date=\(visitTime), date=\(visitDate)", level: .debug)
                            }

                            browseHist.append(BrowserHistoryLog(
                                browser: "Firefox",
                                url: histURL,
                                title: histURL, // Use URL as title for now
                                last_visit: visitTime,
                                date: getCurrentDateTimeString()
                            ))
                        }

                        sqlite3_finalize(queryStatement)
                        // logMessage("Query completed: found \(entryCount) entries from profile \(each)", level: .info)
                        logMessage("Updated firefoxChecked to: \(firefoxChecked ?? 0)", level: .debug)
                    } else {
                        let errorMsg = String(cString: sqlite3_errmsg(db))
                        logError("Could not prepare query for Firefox History - SQLite error: \(errorMsg)", context: "FirefoxHistory")
                    }
                    
                    sqlite3_close(db)
                    try? FileManager.default.removeItem(atPath: temporaryPath)
                    logMessage("Closed database and removed temporary file", level: .debug)
                }
            }
        } else {
            logMessage("Firefox Profiles folder not found at: /Users/\(username)/Library/Application Support/Firefox/Profiles/", level: .warning)
        }
        
        // logMessage("=== Completed Firefox history collection: \(browseHist.count) total entries ===", level: .info)
        return browseHist
    }
    
    func getEdgeHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let edgeProfilesPath = "/Users/\(username)/Library/Application Support/Microsoft Edge/"
        
        if !fileMan.fileExists(atPath: edgeProfilesPath) {
            logMessage("Edge profiles folder not found at: \(edgeProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: edgeProfilesPath, browser: "Edge", checkedVariable: &edgeChecked)
    }
    
    func getOperaHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let operaProfilesPath = "/Users/\(username)/Library/Application Support/com.operasoftware.Opera/"
        
        if !fileMan.fileExists(atPath: operaProfilesPath) {
            logMessage("Opera profiles folder not found at: \(operaProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: operaProfilesPath, browser: "Opera", checkedVariable: &operaChecked)
    }
    
    func getYandexHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let yandexProfilesPath = "/Users/\(username)/Library/Application Support/Yandex/YandexBrowser/"
        
        if !fileMan.fileExists(atPath: yandexProfilesPath) {
            logMessage("Yandex profiles folder not found at: \(yandexProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: yandexProfilesPath, browser: "Yandex", checkedVariable: &yandexChecked)
    }
    
    func getVivaldiHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let vivaldiProfilesPath = "/Users/\(username)/Library/Application Support/Vivaldi/"
        
        if !fileMan.fileExists(atPath: vivaldiProfilesPath) {
            logMessage("Vivaldi profiles folder not found at: \(vivaldiProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: vivaldiProfilesPath, browser: "Vivaldi", checkedVariable: &vivaldiChecked)
    }
    
    func getBraveHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        let username = NSUserName()
        let braveProfilesPath = "/Users/\(username)/Library/Application Support/BraveSoftware/Brave-Browser/"
        
        if !fileMan.fileExists(atPath: braveProfilesPath) {
            logMessage("Brave profiles folder not found at: \(braveProfilesPath)", level: .debug)
            return []
        }
        
        return try processChromeBasedProfiles(profilesPath: braveProfilesPath, browser: "Brave", checkedVariable: &braveChecked)
    }
    
    func getBrowserHistories() throws -> [BrowserHistoryLog]? {
        var browseHist: [BrowserHistoryLog] = []
        
        // Get Safari histories
        do {
            if let safariHistories = try getSafariHistories() {
                browseHist.append(contentsOf: safariHistories)
                logMessage("Safari histories collected: \(safariHistories.count) entries", level: .debug)
            } else {
                logMessage("No Safari histories found", level: .debug)
            }
        } catch {
            logError("Error getting Safari histories: \(error)", context: "BrowserHistory")
        }

        // Get Chrome histories
        do {
            if let chromeHistories = try getChromeHistories() {
                browseHist.append(contentsOf: chromeHistories)
                logMessage("Chrome histories collected: \(chromeHistories.count) entries", level: .debug)
            } else {
                logMessage("No Chrome histories found", level: .debug)
            }
        } catch {
            logError("Error getting Chrome histories: \(error)", context: "BrowserHistory")
        }

        // Get Firefox histories
        do {
            if let firefoxHistories = try getFirefoxHistories() {
                browseHist.append(contentsOf: firefoxHistories)
                logMessage("Firefox histories collected: \(firefoxHistories.count) entries", level: .debug)
            } else {
                logMessage("No Firefox histories found", level: .debug)
            }
        } catch {
            logError("Error getting Firefox histories: \(error)", context: "BrowserHistory")
        }

        // Get Edge histories
        do {
            if let edgeHistories = try getEdgeHistories() {
                browseHist.append(contentsOf: edgeHistories)
                logMessage("Edge histories collected: \(edgeHistories.count) entries", level: .debug)
            } else {
                logMessage("No Edge histories found", level: .debug)
            }
        } catch {
            logError("Error getting Edge histories: \(error)", context: "BrowserHistory")
        }

        // Get Opera histories
        do {
            if let operaHistories = try getOperaHistories() {
                browseHist.append(contentsOf: operaHistories)
                logMessage("Opera histories collected: \(operaHistories.count) entries", level: .debug)
            } else {
                logMessage("No Opera histories found", level: .debug)
            }
        } catch {
            logError("Error getting Opera histories: \(error)", context: "BrowserHistory")
        }

        // Get Yandex histories
        do {
            if let yandexHistories = try getYandexHistories() {
                browseHist.append(contentsOf: yandexHistories)
                logMessage("Yandex histories collected: \(yandexHistories.count) entries", level: .debug)
            } else {
                logMessage("No Yandex histories found", level: .debug)
            }
        } catch {
            logError("Error getting Yandex histories: \(error)", context: "BrowserHistory")
        }

        // Get Vivaldi histories
        do {
            if let vivaldiHistories = try getVivaldiHistories() {
                browseHist.append(contentsOf: vivaldiHistories)
                logMessage("Vivaldi histories collected: \(vivaldiHistories.count) entries", level: .debug)
            } else {
                logMessage("No Vivaldi histories found", level: .debug)
            }
        } catch {
            logError("Error getting Vivaldi histories: \(error)", context: "BrowserHistory")
        }

        // Get Brave histories
        do {
            if let braveHistories = try getBraveHistories() {
                browseHist.append(contentsOf: braveHistories)
                logMessage("Brave histories collected: \(braveHistories.count) entries", level: .debug)
            } else {
                logMessage("No Brave histories found", level: .debug)
            }
        } catch {
            logError("Error getting Brave histories: \(error)", context: "BrowserHistory")
        }

        return browseHist
    }
    
    @objc func sendBrowserHistories() {
        logMonitoringEvent("Starting browser history collection", details: "Checking all supported browsers")
        
        var browserHistories: [BrowserHistoryLog] = []
        do {
            browserHistories = try getBrowserHistories() ?? []
            // logMessage("Browser history collection completed: \(browserHistories.count) entries found", level: .info)
            
            // If no entries found, log debug info
            if browserHistories.count == 0 {
            }
        } catch {
            logError("Error reading browser history: \(error)", context: "BrowserHistory")
            return
        }
        
        if (browserHistories.count > 0) {
            // logMessage("Sending \(browserHistories.count) browser history entries to server", level: .info)
            
            // Log a sample of the histories for debugging
            let sampleCount = min(3, browserHistories.count)
            for i in 0..<sampleCount {
                let history = browserHistories[i]
                logMessage("Sample history \(i+1): \(history.browser) - \(history.url)", level: .debug)
            }
            
            // Send data in chunks
            sendDataInChunks(data: browserHistories, eventType: "BrowserHistory", chunkSize: 1000)
            lastBrowserHistorySent = Date()
            logSuccess("Browser history data sent to server", details: "\(browserHistories.count) entries")
        } else {
            // logMessage("No browser history entries found to send. This may be normal if no new history since last check.", level: .info)
        }
    }

    @objc func checkMonitorChecker() {
        let checkers = NSWorkspace.shared.runningApplications.filter({ app in
            app.bundleIdentifier != nil && app.bundleIdentifier! == CHECKER_IDENTIFIER
        })
        if checkers.count > 1 {
            checkers.last?.terminate()
        } else if checkers.isEmpty {
            let task = Process()
            task.executableURL = URL(fileURLWithPath: "/Applications/MonitorChecker.app/Contents/MacOS/MonitorChecker")
            do {
                try task.run()
                debugPrint("Run process")
            } catch {
                debugPrint("Error: \(error)")
            }
        }
    }

    @objc func sendTicEvent() {
        guard !clientToken.isEmpty && selectedMonitorId > 0 else { return }
        logMonitoringEvent("Sending tic event to server")
        checkMonitorChecker()
        
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Failed to build endpoint for tic event", context: "TicEvent")
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        if activeRunning == true {
            // Get username from settings
            let username = storage.string(forKey: "username") ?? NSUserName()
            
            // Prepare request data
            let postData: [String: Any] = [
                "Event": "Tic",
                "MonitorId": selectedMonitorId,
                "Version": APP_VERSION,
                "MacAddress": macAddress,
                "Username": username
            ]
            
            logMessage("Tic event data: \(postData)", level: .debug)
            
            // Convert to JSON
            guard let jsonData = try? JSONSerialization.data(withJSONObject: postData) else {
                logError("Failed to serialize JSON data for tic event", context: "TicEvent")
                return
            }
            
            // Create request
            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.setValue("Bearer \(clientToken)", forHTTPHeaderField: "Authorization")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
            request.httpBody = jsonData
            
            logMessage("Sending tic event to: \(urlString)", level: .debug)
            logMessage("Authorization headers redacted", level: .debug)
            logMessage("Request body size: \(jsonData.count) bytes", level: .debug)
            
            // Send request
            let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                DispatchQueue.main.async {
                                    if let error = error {
                    self?.logError("Tic event network error: \(error)", context: "TicEvent")
                    self?.logError("Error details: \(error.localizedDescription)", context: "TicEvent")
                    self?.isServerConnected = false
                    self?.checkServerConnectionChange()
                    return
                }
                    
                    if let httpResponse = response as? HTTPURLResponse {
                        self?.logMessage("Tic event HTTP response: \(httpResponse.statusCode)", level: .debug)
                        self?.logMessage("Response headers: \(httpResponse.allHeaderFields)", level: .debug)
                        
                        if httpResponse.statusCode == 200 {
                            self?.logSuccess("Tic event sent successfully", details: "HTTP \(httpResponse.statusCode)")
                            self?.isServerConnected = true
                            self?.checkServerConnectionChange()
                            
                            if let data = data, let responseString = String(data: data, encoding: .utf8) {
                                self?.logMessage("Response data size: \(data.count) bytes", level: .debug)
                                self?.lastTicSent = Date()
                            }
                        } else {
                            self?.logError("Tic event HTTP error: \(httpResponse.statusCode)", context: "TicEvent")
                            self?.isServerConnected = false
                            self?.checkServerConnectionChange()
                        }
                    } else {
                        self?.logError("No HTTP response received for tic event", context: "TicEvent")
                        self?.isServerConnected = false
                        self?.checkServerConnectionChange()
                    }
                }
            }
            task.resume()
        } else {
            logMessage("Tic event skipped - app not active", level: .debug)
        }
    }

    @objc func sendKeyLogs() {
        logMessage("sendKeyLogs called - keyLogs count: \(keyLogs.count)", level: .debug)
        
        if self.keyLogs.count > 0 {
            logMonitoringEvent("Sending key logs", details: "Count: \(keyLogs.count)")
            
            // Log a sample of the key logs for debugging
            let sampleCount = min(3, keyLogs.count)
            for i in 0..<sampleCount {
                let log = keyLogs[i]
                logMessage("Sample key log \(i+1): \(log.date) - \(log.application) - \(log.key)", level: .debug)
            }
            
            
                // Send data in chunks
                sendDataInChunks(data: self.keyLogs, eventType: "KeyLog", chunkSize: 500)
                self.keyLogs.removeAll(keepingCapacity: true) // Optimized memory management
            lastKeyLogSent = Date()
                logSuccess("Key logs sent and cleared", details: "\(keyLogs.count) keys")
        } else {
            logMessage("No key logs to send", level: .debug)
        }
    }
    
    @objc func TakeScreenShotsAndPost() {
        logMonitoringEvent("Starting screenshot capture")
        
        // Check screen recording permission first
        if !isScreenRecordingEnabled() {
            logError("Screen recording permission not granted", context: "Screenshot")
            requestScreenRecordingPermission()
            return
        }
        
        // Use CGWindowListCreateImage for screenshot capture (available in macOS 14.0)
        let displayCount = NSScreen.screens.count
        
        if (displayCount == 0) {
            logError("No displays found", context: "Screenshot")
            return
        }
        
        logMessage("Found \(displayCount) display(s)", level: .debug)
        
        // Capture single screenshot of all displays combined
        let filename = NSTemporaryDirectory() + "screenshot.jpg"
        
        // Use CGWindowListCreateImage to capture the entire screen area
        if let image = CGWindowListCreateImage(
            CGRect.null,
            .optionOnScreenOnly,
            kCGNullWindowID,
            .bestResolution
        ) {
            let bitmapRep = NSBitmapImageRep(cgImage: image)
            let options: [NSBitmapImageRep.PropertyKey: Any] = [.compressionFactor: 0.21]
            
            if let jpegData = bitmapRep.representation(using: .jpeg, properties: options) {
                do {
                    try jpegData.write(to: URL(fileURLWithPath: filename), options: .atomic)
                    logMessage("Screenshot saved: \(filename) (\(jpegData.count) bytes)", level: .debug)
                    postImage(path: filename)
                } catch {
                    logError("Failed to save screenshot: \(error)", context: "Screenshot")
                }
            }
        } else {
            logError("Failed to capture screenshot", context: "Screenshot")
        }
        
        logSuccess("Screenshot capture completed", details: "1 combined screenshot")
    }
    

    

    
    func waitFor (_ wait: inout Bool) {
        while (wait) {
            RunLoop.current.run(mode: .default, before: Date(timeIntervalSinceNow: 0.1))
        }
    }
    
    func postImage(path: String) {
        guard !clientToken.isEmpty && selectedMonitorId > 0 else { return }
        logMonitoringEvent("Uploading screenshot", details: "File: \(path)")
        
        // Build screenshot upload endpoint with correct query parameter
        guard let baseUrlString = buildEndpoint(true) else {
            logError("Failed to build base endpoint for screenshot upload", context: "Screenshot")
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        // Add the required ?type=upload query parameter for screenshot uploads
        let urlString = baseUrlString + "?type=upload"
        guard let url = URL(string: urlString) else {
            logError("Failed to create URL for screenshot upload: \(urlString)", context: "Screenshot")
            return
        }
        
        guard let imageData = try? Data(contentsOf: URL(fileURLWithPath: path)) else {
            logError("Failed to read image data from: \(path)", context: "Screenshot")
            return
        }
        
        logMessage("Screenshot data size: \(imageData.count) bytes", level: .debug)
        
        // Get current timestamp for ctime parameter
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        let currentTime = formatter.string(from: Date())
        
        // Get computer name for com_name parameter
        let computerName = Host.current().name ?? "Unknown"
        
        // Create multipart form data
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add com_name parameter (required by backend)
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"com_name\"\r\n\r\n".data(using: .utf8)!)
        body.append(computerName.data(using: .utf8)!)
        body.append("\r\n".data(using: .utf8)!)
        
        // Bound monitor identity is included for server consistency checking.
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"monitor_id\"\r\n\r\n".data(using: .utf8)!)
        body.append(String(selectedMonitorId).data(using: .utf8)!)
        body.append("\r\n".data(using: .utf8)!)
        // Add ctime parameter (required by backend)
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"ctime\"\r\n\r\n".data(using: .utf8)!)
        body.append(currentTime.data(using: .utf8)!)
        body.append("\r\n".data(using: .utf8)!)
        
        // Add file data
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"imagefile\"; filename=\"screenshot.jpg\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        body.append(imageData)
        body.append("\r\n".data(using: .utf8)!)
        
        // End boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create request
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(clientToken)", forHTTPHeaderField: "Authorization")
        request.httpBody = body
        
        logMessage("Sending screenshot to: \(urlString)", level: .debug)
        logMessage("Screenshot parameters - com_name: \(computerName), ctime: \(currentTime)", level: .debug)
        logMessage("Request body size: \(body.count) bytes", level: .debug)
        
        // Send request
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    self?.logError("Screenshot upload network error: \(error)", context: "Screenshot")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse {
                    self?.logMessage("Screenshot HTTP response: \(httpResponse.statusCode)", level: .debug)
                }
                
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    self?.logSuccess("Screenshot uploaded successfully", details: "Response: \(responseString)")
                    self?.lastScreenshotSent = Date()
                    
                    // Parse server response for confirmation
                    if let jdata = self?.convertToDictionary(text: responseString) {
                        // Check for success status
                        if let status = jdata["status"] as? String, status == "success" {
                            // self?.logMessage("Screenshot upload confirmed by server", level: .info)
                            
                            // Log additional server response data
                            if let dataObj = jdata["data"] as? [String: Any] {
                                if let filename = dataObj["filename"] as? String {
                                    // self?.logMessage("Screenshot saved as: \(filename)", level: .info)
                                }
                                if let fileSize = dataObj["file_size"] as? Int {
                                    self?.logMessage("File size: \(fileSize) bytes", level: .debug)
                                }
                                if let width = dataObj["image_width"] as? Int, let height = dataObj["image_height"] as? Int {
                                    self?.logMessage("Image dimensions: \(width)x\(height)", level: .debug)
                                }
                            }
                        } else if let error = jdata["error"] as? String {
                            self?.logError("Server error: \(error)", context: "Screenshot")
                        } else {
                            self?.logMessage("Server response received but status unclear", level: .warning)
                        }
                    } else {
                        self?.logMessage("Could not parse server response JSON", level: .warning)
                    }
                } else {
                    self?.logError("No response data received for screenshot upload", context: "Screenshot")
                }
            }
        }
        task.resume()
    }
    
    func convertToDictionary(text: String) -> [String: Any]? {
        if let data = text.data(using: .utf8) {
            do {
                return try JSONSerialization.jsonObject(with: data, options: []) as? [String: Any]
            } catch {
                debugPrint(error.localizedDescription)
            }
        }
        return nil
    }
    
    func getMacAddress() -> String {
        let theTask = Process()
        let taskOutput = Pipe()
        theTask.launchPath = "/sbin/ifconfig"
        theTask.standardOutput = taskOutput
        theTask.standardError = taskOutput
        theTask.arguments = ["en0"]
        
        theTask.launch()
        theTask.waitUntilExit()
        
        let taskData = taskOutput.fileHandleForReading.readDataToEndOfFile()
        
        if let stringResult = NSString(data: taskData, encoding: String.Encoding.utf8.rawValue) {
            if stringResult != "ifconfig: interface en0 does not exist" {
                let f = stringResult.range(of: "ether")
                if f.location != NSNotFound {
                    let sub = stringResult.substring(from: f.location + f.length)
                    let start = sub.index(sub.startIndex, offsetBy: 1)
                    let end = sub.index(sub.startIndex, offsetBy: 18)
                    let range = start ..< end
                    let result = sub[range]
                    let address = String(result)
                    return address
                }
            }
        }
        
        return ""
    }
    
    func getClientIPAddress() -> String {
        // Try to get local IP addresses first
        let interfaces = ["en0", "en1", "en2", "en3", "en4", "en5", "en6", "en7", "en8", "en9"]
        
        for interface in interfaces {
            let task = Process()
            let output = Pipe()
            task.launchPath = "/sbin/ifconfig"
            task.arguments = [interface, "inet"]
            task.standardOutput = output
            task.standardError = output
            
            do {
                task.launch()
                task.waitUntilExit()
                
                let data = output.fileHandleForReading.readDataToEndOfFile()
                if let result = String(data: data, encoding: .utf8) {
                    let lines = result.components(separatedBy: .newlines)
                    for line in lines {
                        if line.contains("inet ") && !line.contains("127.0.0.1") {
                            let components = line.components(separatedBy: " ")
                            for component in components {
                                if component.contains(".") && component.components(separatedBy: ".").count == 4 {
                                    // Check if it's a local network IP (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
                                    let ipParts = component.components(separatedBy: ".")
                                    if ipParts.count == 4 {
                                        let firstOctet = Int(ipParts[0]) ?? 0
                                        let secondOctet = Int(ipParts[1]) ?? 0
                                        
                                        // Local network ranges
                                        if (firstOctet == 192 && secondOctet == 168) ||
                                           (firstOctet == 10) ||
                                           (firstOctet == 172 && secondOctet >= 16 && secondOctet <= 31) {
                                            logMessage("Found local IP: \(component) on interface \(interface)", level: .debug)
                                            return component
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch {
                logMessage("Failed to check interface \(interface): \(error)", level: .debug)
            }
        }
        
        // Fallback: try to get any non-loopback IP
        for interface in interfaces {
            let task = Process()
            let output = Pipe()
            task.launchPath = "/sbin/ifconfig"
            task.arguments = [interface, "inet"]
            task.standardOutput = output
            task.standardError = output
            
            do {
                task.launch()
                task.waitUntilExit()
                
                let data = output.fileHandleForReading.readDataToEndOfFile()
                if let result = String(data: data, encoding: .utf8) {
                    let lines = result.components(separatedBy: .newlines)
                    for line in lines {
                        if line.contains("inet ") && !line.contains("127.0.0.1") {
                            let components = line.components(separatedBy: " ")
                            for component in components {
                                if component.contains(".") && component.components(separatedBy: ".").count == 4 {
                                    logMessage("Found fallback IP: \(component) on interface \(interface)", level: .debug)
                                    return component
                                }
                            }
                        }
                    }
                }
            } catch {
                logMessage("Failed to check interface \(interface) for fallback: \(error)", level: .debug)
            }
        }
        
        logMessage("No local IP address found", level: .warning)
        return "Unknown"
    }
    
    @objc func applicationDidTerminate(_ notification: Notification) {
        guard let app = notification.userInfo?[NSWorkspace.applicationUserInfoKey] as? NSRunningApplication,
              let bundleID = app.bundleIdentifier,
              browserBundleIDs.contains(bundleID) else {
            return
        }
        
        debugPrint("Browser terminated: \(bundleID)")
        // Wait a short moment to ensure the browser has fully closed and released the database
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            self?.sendBrowserHistories()
        }
    }

    private func startKeyboardMonitoring() {
        guard selectedMonitorId>0 else {return}
        // Check permission before starting monitoring
        if isInputMonitoringEnabled() {
            // logMessage("Accessibility permission already granted, starting keyboard monitoring", level: .info)
            setupKeyboardMonitoring()
        } else {
            // Start a timer to periodically check for permission
            // logMessage("Accessibility permission not granted, requesting access", level: .info)
            requestAccessibilityPermission()
            
            // Use a more robust permission checking mechanism
            var permissionCheckCount = 0
            let maxPermissionChecks = 60 // Check for up to 60 seconds
            
            Timer.scheduledTimer(withTimeInterval: 1.0, repeats: true) { [weak self] timer in
                guard let self = self else {
                    timer.invalidate()
                    return
                }
                
                permissionCheckCount += 1
                
                if self.isInputMonitoringEnabled() {
                    // Permission granted, start monitoring
                    // self.logMessage("Accessibility permission granted after \(permissionCheckCount) seconds, starting keyboard monitoring", level: .info)
                    self.setupKeyboardMonitoring()
                    timer.invalidate()
                } else if permissionCheckCount >= maxPermissionChecks {
                    // Give up after max attempts
                    self.logError("Accessibility permission not granted after \(maxPermissionChecks) seconds, giving up", context: "Permission")
                    timer.invalidate()
                } else if permissionCheckCount % 10 == 0 {
                    // Log progress every 10 seconds
                    // self.logMessage("Still waiting for accessibility permission... (\(permissionCheckCount)s)", level: .info)
                }
                
                // Don't add permission denied logs to keyLogs as it floods the logs
            }
        }
    }
    
    func debugPrint(_ message: String) {
        // Debug printing disabled for production performance
    }
    
    private func setupKeyboardMonitoring() {
        guard selectedMonitorId>0 else {return}
        debugPrint("Setting up keyboard monitoring...")
        
        // Clean up existing event tap if it exists
        if let existingTap = eventTap {
            CGEvent.tapEnable(tap: existingTap, enable: false)
            eventTap = nil
        }
        
        // Check permissions first
        if !isInputMonitoringEnabled() {
            debugPrint("Input monitoring permission not granted")
            requestAccessibilityPermission()
            return
        }
        
        // Create event mask for keyboard events
        let eventMask = CGEventMask(1 << CGEventType.keyDown.rawValue)
        
        // Create the event tap
        guard let newEventTap = CGEvent.tapCreate(
            tap: .cghidEventTap,
            place: .headInsertEventTap,
            options: .defaultTap,
            eventsOfInterest: eventMask,
            callback: handleKeyDownCallback,
            userInfo: UnsafeMutableRawPointer(Unmanaged.passUnretained(self).toOpaque())
        ) else {
            debugPrint("Failed to create event tap")
            return
        }
        
        // Create a run loop source
        let runLoopSource = CFMachPortCreateRunLoopSource(kCFAllocatorDefault, newEventTap, 0)
        
        // Add to run loop
        CFRunLoopAddSource(CFRunLoopGetCurrent(), runLoopSource, .commonModes)
        
        // Enable the event tap
        CGEvent.tapEnable(tap: newEventTap, enable: true)
        
        // Store the event tap
        eventTap = newEventTap
        
        debugPrint("Keyboard monitoring setup complete")
        storage.set(true, forKey: "input-monitoring-enabled")
    }

    func checkModifierKeys(_ event: CGEvent) -> String {
        let flags = event.flags
        let keyCode = event.getIntegerValueField(.keyboardEventKeycode)
        var modifiers: [String] = []
        
        if flags.contains(.maskCommand) {
            modifiers.append("Command")
        }
        if flags.contains(.maskAlternate) {
            modifiers.append("Option")
        }
        if flags.contains(.maskControl) {
            modifiers.append("Control")
        }
        // Only show Shift if the character is not printable (like arrow keys, function keys, etc.)
        if flags.contains(.maskShift) {
            // Get the character from the event to check if it's printable
            if let nsEvent = NSEvent(cgEvent: event) {
                let character = nsEvent.charactersIgnoringModifiers ?? ""
                debugPrint("Shift detected - KeyCode: \(keyCode), Character: '\(character)', Character count: \(character.count)")
                
                // Check if this is a special key that should always show Shift
                let specialKeys = [123, 124, 125, 126, 36, 48, 51, 53, 76, 116, 117, 121, 115, 119, 96, 97, 98, 99, 100, 101, 103, 105, 107, 109, 111, 122, 120, 118]
                if specialKeys.contains(Int(keyCode)) {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - keyCode \(keyCode) is a special key")
                } else if character.isEmpty {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - character is empty")
                } else {
                    // Check if character is actually printable (not control characters)
                    let printableSet = CharacterSet.letters.union(CharacterSet.decimalDigits).union(CharacterSet.punctuationCharacters).union(CharacterSet.symbols).union(CharacterSet.whitespaces)
                    if character.rangeOfCharacter(from: printableSet) != nil {
                        debugPrint("Not adding Shift modifier - character '\(character)' is printable")
                    } else {
                        modifiers.append("Shift")
                        debugPrint("Adding Shift modifier - character '\(character)' is not printable")
                    }
                }
            } else {
                // If we can't get the character, show Shift for non-printable key codes
                let nonPrintableKeyCodes = [123, 124, 125, 126, 36, 48, 51, 53, 76, 116, 117, 121, 115, 119, 96, 97, 98, 99, 100, 101, 103, 105, 107, 109, 111, 122, 120, 118]
                if nonPrintableKeyCodes.contains(Int(keyCode)) {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - keyCode \(keyCode) is in non-printable list")
                } else {
                    debugPrint("Not adding Shift modifier - keyCode \(keyCode) is not in non-printable list")
                }
            }
        }
        // Only include Fn if it's not an arrow key (arrow keys often have Fn automatically included)
        // if flags.contains(.maskSecondaryFn) && ![123, 124, 125, 126].contains(Int(keyCode)) {
        //     modifiers.append("Fn")
        // }
        if flags.contains(.maskAlphaShift) {
            modifiers.append("Caps Lock")
        }
        
        return modifiers.isEmpty ? "" : (modifiers.joined(separator: "+") + "+")
    }

    // Prevent window creation for background app
    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        // Don't show any windows when app is reopened
        return false
    }
    
    // MARK: - Settings Management
    
    private func getSettingsFilePath() -> String {
        let appSupportPath = NSHomeDirectory() + "/Library/Application Support/MonitorClient"
        
        // Create directory if it doesn't exist
        if !FileManager.default.fileExists(atPath: appSupportPath) {
            try? FileManager.default.createDirectory(atPath: appSupportPath, withIntermediateDirectories: true, attributes: nil)
        }
        
        return appSupportPath + "/settings.plist"
    }
    
    private func loadSettings() {
        let settingsPath = getSettingsFilePath()
        
        if let settings = NSDictionary(contentsOfFile: settingsPath) {
            if let username = settings["username"] as? String {
                storage.set(username, forKey: "username")
                // logMessage("Loaded username from settings: \(username)", level: .info)
            }
            if let serverIP = settings["serverIP"] as? String {
                // Extract IP from full address if needed
                let ip = serverIP.contains(":") ? String(serverIP.split(separator: ":")[0]) : serverIP
                storage.set(ip, forKey: "server-ip")
                // logMessage("Loaded server IP from settings: \(ip)", level: .info)
            }
            if let serverPort = settings["serverPort"] as? String {
                storage.set(serverPort, forKey: "server-port")
                // logMessage("Loaded server port from settings: \(serverPort)", level: .info)
            }
        } else {
            // logMessage("No settings file found, using defaults", level: .info)
        }
    }
    
    // MARK: - Configuration Management
    
    private func fetchConfigurationFromAPI() {
        guard !isConfigUpdating else {
            logMessage("Configuration update already in progress, skipping", level: .debug)
            return
        }
        
        isConfigUpdating = true
        // logMessage("Fetching configuration from API", level: .info)
        
        guard let serverIP = storage.string(forKey: "server-ip"),
              let serverPort = storage.string(forKey: "server-port") else {
            logMessage("Server configuration not available, using defaults", level: .warning)
            isConfigUpdating = false
            return
        }
        
        let scheme = serverPort == "8924" ? "http" : "https"
        let configURL = "\(scheme)://\(serverIP):\(serverPort)/webapi_config.php?platform=macos"
        
        guard let url = URL(string: configURL) else {
            logMessage("Invalid configuration URL: \(configURL)", level: .error)
            isConfigUpdating = false
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.timeoutInterval = 10.0
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            DispatchQueue.main.async {
                self?.isConfigUpdating = false
                
                if let error = error {
                    self?.logMessage("Configuration fetch failed: \(error.localizedDescription)", level: .error)
                    return
                }
                
                guard let data = data else {
                    self?.logMessage("No configuration data received", level: .error)
                    return
                }
                
                do {
                    let configResponse = try JSONDecoder().decode(ConfigResponse.self, from: data)
                    
                    if configResponse.success, let config = configResponse.data {
                        self?.updateConfiguration(config: config)
                        // self?.logMessage("Configuration updated successfully", level: .info)
                    } else {
                        self?.logMessage("Configuration fetch failed: \(configResponse.error ?? "Unknown error")", level: .error)
                    }
                } catch {
                    self?.logMessage("Configuration parsing failed: \(error.localizedDescription)", level: .error)
                }
            }
        }.resume()
    }
    
    private func updateConfiguration(config: ClientConfig) {
        // Update global interval variables
        TIME_INTERVAL = config.intervals.screenshot
        TIC_INTERVAL = config.intervals.tic
        HISTORY_INTERVAL = config.intervals.history
        KEY_INTERVAL = config.intervals.keylog
        USB_INTERVAL = config.intervals.usb
        CONFIG_REFRESH_INTERVAL = config.intervals.config_refresh
        
        // Store current configuration
        currentConfig = config
        lastConfigUpdate = Date()
        
        // Log configuration update
        // logMessage("Configuration updated:", level: .info)
        // logMessage("  Screenshot: \(TIME_INTERVAL)s", level: .info)
        // logMessage("  Tic: \(TIC_INTERVAL)s", level: .info)
        // logMessage("  History: \(HISTORY_INTERVAL)s", level: .info)
        // logMessage("  Keylog: \(KEY_INTERVAL)s", level: .info)
        // logMessage("  USB: \(USB_INTERVAL)s", level: .info)
        // logMessage("  Config Refresh: \(CONFIG_REFRESH_INTERVAL)s", level: .info)
        
        // Restart configuration timer with new interval
        restartConfigTimer()
    }
    
    private func restartConfigTimer() {
        configTimer?.invalidate()
        configTimer = Timer.scheduledTimer(withTimeInterval: TimeInterval(CONFIG_REFRESH_INTERVAL), repeats: true) { [weak self] _ in
            self?.fetchConfigurationFromAPI()
        }
        logMessage("Configuration refresh timer restarted with \(CONFIG_REFRESH_INTERVAL)s interval", level: .debug)
    }
    
    @objc private func manualConfigRefresh() {
        // logMessage("Manual configuration refresh requested", level: .info)
        fetchConfigurationFromAPI()
    }
    
    private func saveSettings(username: String, serverIP: String, serverPort: String) {
        let settingsPath = getSettingsFilePath()
        let settings: [String: Any] = [
            "username": username,
            "serverIP": serverIP,
            "serverPort": serverPort
        ]
        
        if let plistData = try? PropertyListSerialization.data(fromPropertyList: settings, format: .xml, options: 0) {
            try? plistData.write(to: URL(fileURLWithPath: settingsPath))
            // logMessage("Settings saved to: \(settingsPath)", level: .info)
        }
    }
    
    private func clientAuthRequest(_ body: [String: Any], authenticated: Bool = false) -> [String: Any]? {
        guard let address = storage.string(forKey: "server-ip") else { return nil }
        let host = address.split(separator: ":").first.map(String.init) ?? address
        let port = storage.string(forKey: "server-port") ?? "8924"
        let scheme = port == "8924" ? "http" : "https"
        guard let url = URL(string: "\(scheme)://\(host):\(port)/client_auth.php"),
              let bytes = try? JSONSerialization.data(withJSONObject: body) else { return nil }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.timeoutInterval = 12
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if authenticated && !clientToken.isEmpty {
            request.setValue("Bearer \(clientToken)", forHTTPHeaderField: "Authorization")
        }
        request.httpBody = bytes
        let semaphore = DispatchSemaphore(value: 0)
        var result: [String: Any]?
        URLSession.shared.dataTask(with: request) { data, _, _ in
            if let data = data {
                result = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
            }
            semaphore.signal()
        }.resume()
        _ = semaphore.wait(timeout: .now() + 12)
        return result
    }

    private var activeSettingsWindow: NSWindow?

    private func openLoginDialog() { openUnifiedSettingsWindow() }
    @objc private func openSettingsDialog() { openUnifiedSettingsWindow() }

    // Server, User and Monitor Settings share a single compact, scrollable window.
    private func openUnifiedSettingsWindow() {
        if let existing = activeSettingsWindow {
            existing.makeKeyAndOrderFront(nil)
            NSApp.activate(ignoringOtherApps: true)
            return
        }
        let width: CGFloat = 510
        let window = NSWindow(contentRect: NSRect(x: 0, y: 0, width: width, height: 650),
            styleMask: [.titled, .closable, .miniaturizable], backing: .buffered, defer: false)
        window.title = "MonitorClient · Settings"
        window.titleVisibility = .hidden
        window.titlebarAppearsTransparent = true
        window.isReleasedWhenClosed = false
        window.center()
        activeSettingsWindow = window
        defer {
            if activeSettingsWindow === window { activeSettingsWindow = nil }
            window.orderOut(nil)
        }

        let scroll = NSScrollView(frame: NSRect(x: 0, y: 0, width: width, height: 650))
        scroll.hasVerticalScroller = true
        scroll.autohidesScrollers = true
        scroll.drawsBackground = false
        let document = MonitorSettingsDocumentView()
        document.translatesAutoresizingMaskIntoConstraints = false
        scroll.documentView = document
        window.contentView = scroll
        let stack = NSStackView()
        stack.orientation = .vertical
        stack.alignment = .centerX
        stack.spacing = 10
        stack.translatesAutoresizingMaskIntoConstraints = false
        document.addSubview(stack)
        NSLayoutConstraint.activate([
            document.widthAnchor.constraint(equalTo: scroll.contentView.widthAnchor),
            stack.topAnchor.constraint(equalTo: document.topAnchor, constant: 8),
            stack.leadingAnchor.constraint(equalTo: document.leadingAnchor, constant: 20),
            stack.trailingAnchor.constraint(equalTo: document.trailingAnchor, constant: -20),
            document.bottomAnchor.constraint(equalTo: stack.bottomAnchor, constant: 12)
        ])
        func text(_ value: String, bold: Bool = false) -> NSTextField {
            let field = NSTextField(labelWithString: value)
            field.font = NSFont.systemFont(ofSize: bold ? 15 : 12, weight: bold ? .semibold : .regular)
            return field
        }
        func entry(_ placeholder: String, value: String = "") -> NSTextField {
            let field = NSTextField()
            field.placeholderString = placeholder
            field.stringValue = value
            field.widthAnchor.constraint(equalToConstant: 330).isActive = true
            return field
        }
        func section(_ title: String) -> NSStackView {
            let pane = NSStackView()
            pane.orientation = .vertical
            pane.alignment = .leading
            pane.spacing = 8
            pane.widthAnchor.constraint(equalToConstant: 450).isActive = true
            pane.addArrangedSubview(text(title, bold: true))
            return pane
        }
        func line(_ title: String, _ control: NSView) -> NSStackView {
            let caption = text(title)
            caption.widthAnchor.constraint(equalToConstant: 100).isActive = true
            let row = NSStackView(views: [caption, control])
            row.orientation = .horizontal
            row.alignment = .centerY
            row.spacing = 8
            return row
        }
        func rule() {
            let divider = NSBox()
            divider.boxType = .separator
            divider.widthAnchor.constraint(equalToConstant: 450).isActive = true
            stack.addArrangedSubview(divider)
        }
        func action(_ title: String, _ key: String) -> NSButton {
            let button = NSButton(title: title, target: nil, action: nil)
            button.identifier = NSUserInterfaceItemIdentifier(key)
            button.bezelStyle = .rounded
            return button
        }
        let brand = NSStackView()
        brand.orientation = .vertical
        brand.alignment = .centerX
        brand.spacing = 3
        let logo = NSImageView(image: NSApp.applicationIconImage)
        logo.imageScaling = .scaleProportionallyUpOrDown
        logo.widthAnchor.constraint(equalToConstant: 43).isActive = true
        logo.heightAnchor.constraint(equalToConstant: 43).isActive = true
        brand.addArrangedSubview(logo)
        let version = text("MonitorClient · v1.5")
        version.textColor = .secondaryLabelColor
        brand.addArrangedSubview(version)
        stack.addArrangedSubview(brand)
        rule()

        let server = section("Server Settings")
        let ipField = entry("Server address", value: (storage.string(forKey: "server-ip") ?? "").split(separator: ":").first.map(String.init) ?? "")
        let portField = entry("Port", value: storage.string(forKey: "server-port") ?? "8925")
        server.addArrangedSubview(line("Server IP", ipField))
        server.addArrangedSubview(line("Port", portField))
        let test = action("Test Connection", "test")
        let status = text("Not tested")
        status.textColor = .secondaryLabelColor
        let checkRow = NSStackView(views: [test, status])
        checkRow.spacing = 10
        server.addArrangedSubview(line("Connection", checkRow))
        stack.addArrangedSubview(server)
        rule()

        let userPane = NSStackView()
        userPane.orientation = .vertical
        userPane.alignment = .leading
        userPane.spacing = 8
        userPane.widthAnchor.constraint(equalToConstant: 450).isActive = true
        let users = NSPopUpButton()
        users.widthAnchor.constraint(equalToConstant: 330).isActive = true
        let userRow = line("User:", users)
        userPane.addArrangedSubview(userRow)
        let unpair = action("Disconnect", "disconnect")
        userPane.addArrangedSubview(line("", unpair))
        let directoryStatus = text("")
        directoryStatus.textColor = .systemRed
        directoryStatus.lineBreakMode = .byWordWrapping
        directoryStatus.maximumNumberOfLines = 3
        directoryStatus.widthAnchor.constraint(equalToConstant: 440).isActive = true
        directoryStatus.isHidden = true
        userPane.addArrangedSubview(directoryStatus)
        let notice = text("")
        notice.textColor = .secondaryLabelColor
        notice.lineBreakMode = .byWordWrapping
        notice.maximumNumberOfLines = 3
        notice.widthAnchor.constraint(equalToConstant: 440).isActive = true
        userPane.addArrangedSubview(notice)
        stack.addArrangedSubview(userPane)
        rule()

        let monitorPane = section("Monitor Settings")
        let picker = NSPopUpButton()
        picker.widthAnchor.constraint(equalToConstant: 330).isActive = true
        let label = entry("Label")
        let remark = entry("Remark")
        let monitorIP = text("—")
        func radioRow(_ choices: [String], selected: String) -> (NSStackView, [NSButton]) {
            let radios = choices.map { title -> NSButton in
                let button = NSButton(radioButtonWithTitle: title, target: nil, action: nil)
                button.state = title == selected ? .on : .off
                return button
            }
            let row = NSStackView(views: radios)
            row.orientation = .horizontal
            row.spacing = 12
            return (row, radios)
        }
        let (osRow, osRadios) = radioRow(["Windows", "Mac", "Ubuntu"], selected: "Mac")
        let (machineRow, machineRadios) = radioRow(["Laptop", "Desktop"], selected: "Laptop")
        func selectedRadio(_ radios: [NSButton]) -> String {
            radios.first(where: { $0.state == .on })?.title ?? radios[0].title
        }
        func chooseRadio(_ radios: [NSButton], value: String) {
            for button in radios { button.state = button.title == value ? .on : .off }
        }
        monitorPane.addArrangedSubview(line("Monitor", picker))
        monitorPane.addArrangedSubview(line("Label", label))
        monitorPane.addArrangedSubview(line("OS", osRow))
        monitorPane.addArrangedSubview(line("Machine", machineRow))
        monitorPane.addArrangedSubview(line("IP address", monitorIP))
        monitorPane.addArrangedSubview(line("Remark", remark))
        let save = action("Save Monitor", "save")
        let lockSettings = action("Lock Settings", "lock")
        let monitorActions = NSStackView(views: [save, lockSettings])
        monitorActions.orientation = .horizontal
        monitorActions.spacing = 10
        monitorPane.addArrangedSubview(line("", monitorActions))
        let monitorNote = text("Monitor assignment is controlled by the server.")
        monitorNote.textColor = .secondaryLabelColor
        monitorPane.addArrangedSubview(monitorNote)
        stack.addArrangedSubview(monitorPane)
        rule()
        let notifications = NSButton(checkboxWithTitle: "Show status notifications",
                                     target: nil, action: nil)
        notifications.state = self.notificationsEnabled ? .on : .off
        stack.addArrangedSubview(notifications)
        let close = action("Close", "close")
        stack.addArrangedSubview(close)

        let coordinator = UnifiedWindowActions()
        coordinator.window = window
        window.delegate = coordinator
        for button in [test, unpair, save, lockSettings, close] {
            button.target = coordinator
            button.action = #selector(UnifiedWindowActions.activate(_:))
        }
        users.target = coordinator
        users.action = #selector(UnifiedWindowActions.selectUser(_:))
        picker.target = coordinator
        picker.action = #selector(UnifiedWindowActions.selectMonitor(_:))

        var availableMonitors: [[String: Any]] = []
        var locked = false
        var monitorSettingsLocked = false
        var pendingUserNo: String? = nil
        var directoryError: String? = nil
        func updateMonitorFields() {
            let id = picker.selectedItem?.representedObject as? Int ?? 0
            guard let monitor = availableMonitors.first(where: { ($0["id"] as? Int) == id }) else {
                label.stringValue = ""
                remark.stringValue = ""
                monitorIP.stringValue = id == -1 ? "Assigned on creation" : "—"
                monitorSettingsLocked = false
                updateState()
                return
            }
            label.stringValue = monitor["name"] as? String ?? ""
            remark.stringValue = monitor["remark"] as? String ?? ""
            monitorIP.stringValue = monitor["ip_address"] as? String ?? "—"
            chooseRadio(osRadios, value: monitor["platform"] as? String ?? "Mac")
            chooseRadio(machineRadios, value: (monitor["type"] as? String ?? "").lowercased() == "desktop" ? "Desktop" : "Laptop")
            monitorSettingsLocked = monitor["settings_locked"] as? Bool ??
                ((monitor["settings_locked"] as? Int ?? 0) == 1)
            updateState()
        }
        func refreshMonitors() {
            guard !self.clientToken.isEmpty else {
                availableMonitors = []
                picker.removeAllItems()
                return
            }
            let restored = self.clientAuthRequest(["action": "restore"], authenticated: true)
            guard restored?["success"] as? Bool == true else {
                availableMonitors = []
                self.clientToken = ""
                self.selectedMonitorId = 0
                picker.removeAllItems()
                return
            }
            availableMonitors = restored?["monitors"] as? [[String: Any]] ?? []
            let currentNo = String(describing: (restored?["user"] as? [String: Any])?["user_no"] ?? "")
            if let item = users.itemArray.first(where: { ($0.representedObject as? String) == currentNo }) {
                users.select(item)
            }
            self.storage.set(currentNo, forKey: "user_no")
            self.selectedMonitorId = restored?["monitor_id"] as? Int ?? 0
            locked = restored?["assignment_locked"] as? Bool ?? false
            picker.removeAllItems()
            for monitor in availableMonitors {
                let id = monitor["id"] as? Int ?? 0
                let name = monitor["name"] as? String ?? "Monitor"
                let platform = monitor["platform"] as? String ?? "—"
                let type = monitor["type"] as? String ?? "Desktop"
                let ip = monitor["ip_address"] as? String ?? "—"
                picker.addItem(withTitle: "\(name) · \(platform) · \(type) · \(ip)")
                picker.lastItem?.representedObject = id
            }
            picker.addItem(withTitle: "Add new...")
            picker.lastItem?.representedObject = -1
            if let item = picker.itemArray.first(where: { ($0.representedObject as? Int) == self.selectedMonitorId }) {
                picker.select(item)
            }
            updateMonitorFields()
        }
        func updateState() {
            users.toolTip = users.selectedItem?.title
            directoryStatus.stringValue = directoryError ?? ""
            directoryStatus.isHidden = directoryError == nil
            let enrolled = !self.clientToken.isEmpty && self.selectedMonitorId > 0
            monitorPane.isHidden = !enrolled && pendingUserNo == nil
            unpair.isHidden = !enrolled
            unpair.isEnabled = enrolled && !locked
            users.isEnabled = !enrolled || !locked
            notice.isHidden = !enrolled || !locked
            if enrolled && locked {
                notice.stringValue = "User selection is locked. Ask your administrator to edit."
                notice.textColor = .systemOrange
            }
            let creating = picker.selectedItem?.representedObject as? Int == -1
            let pendingFirstDevice = pendingUserNo != nil
            save.title = creating ? "Add Monitor" : "Save Monitor"
            save.isEnabled = (enrolled || pendingFirstDevice) && (creating || !monitorSettingsLocked)
            lockSettings.isEnabled = enrolled && !pendingFirstDevice && !creating && !monitorSettingsLocked
            lockSettings.title = monitorSettingsLocked ? "Settings Locked" : "Lock Settings"
            label.isEnabled = !monitorSettingsLocked || creating
            remark.isEnabled = !monitorSettingsLocked || creating
            osRadios.forEach { $0.isEnabled = !monitorSettingsLocked || creating }
            machineRadios.forEach { $0.isEnabled = !monitorSettingsLocked || creating }
            status.stringValue = self.isServerConnected ? "Server reachable" : "Offline / Not configured"
            self.updateTrayStatusDot()
        }
        func saveServer() -> Bool {
            let host = ipField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            let port = portField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            guard !host.isEmpty, !host.contains("/"), let number = Int(port), (1...65535).contains(number) else { return false }
            let oldHost = self.storage.string(forKey: "server-ip") ?? ""
            let oldPort = self.storage.string(forKey: "server-port") ?? "8925"
            if host != oldHost || port != oldPort {
                // Never forward a bearer token to an unsaved, untrusted host.
                let query: [String: Any] = [
                    kSecClass as String: kSecClassGenericPassword,
                    kSecAttrService as String: self.keychainService,
                    kSecAttrAccount as String: self.sessionAccount()
                ]
                SecItemDelete(query as CFDictionary)
                self.clientToken = ""
                self.selectedMonitorId = 0
                self.isServerConnected = false
            }
            self.storage.set(host, forKey: "server-ip")
            self.storage.set(port, forKey: "server-port")
            self.saveSettings(username: self.storage.string(forKey: "username") ?? "",
                              serverIP: host, serverPort: port)
            return true
        }
        func showError(_ message: String) {
            let alert = NSAlert()
            alert.messageText = "MonitorClient"
            alert.informativeText = message
            alert.runModal()
        }
        func refreshUserDirectory() {
            // Unauthenticated clients must explicitly choose a user. Do not
            // visually preselect an identity that has no valid session.
            let previousNo = pendingUserNo ??
                (self.clientToken.isEmpty ? "" : (self.storage.string(forKey: "user_no") ?? ""))
            let response = self.clientAuthRequest(["action":"directory"])
            let records = response?["users"] as? [[String: Any]] ?? []
            if response?["success"] as? Bool != true {
                directoryError = response?["error"] as? String ??
                    "Cannot load users. Verify the server URL, TLS and enrollment configuration."
            } else if records.isEmpty {
                directoryError = "No users available. Add a User or Viewer in the web portal."
            } else {
                directoryError = nil
            }
            users.removeAllItems()
            users.addItem(withTitle: "Select a user…")
            users.lastItem?.representedObject = ""
            for record in records {
                let number = String(describing: record["user_no"] ?? "")
                guard !number.isEmpty else { continue }
                let name = record["full_name"] as? String ?? ""
                let count = record["device_count"] as? Int ?? 0
                users.addItem(withTitle: "\(number): \(name) (\(count) Devices)")
                users.lastItem?.representedObject = number
            }
            if let item = users.itemArray.first(where: {
                ($0.representedObject as? String) == previousNo
            }) {
                users.select(item)
            } else {
                users.selectItem(at: 0)
            }
            updateState()
        }
        coordinator.handler = { [weak self] command in
            guard let self = self else { return }
            switch command {
            case "test":
                let host = ipField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
                let port = portField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
                guard let number = Int(port), (1...65535).contains(number), !host.isEmpty,
                      let url = URL(string: "\(port == "8924" ? "http" : "https")://\(host):\(port)/html/auth/login") else {
                    status.stringValue = "Invalid server address"
                    return
                }
                test.isEnabled = false
                status.stringValue = "Checking…"
                var request = URLRequest(url: url)
                request.timeoutInterval = 6
                URLSession.shared.dataTask(with: request) { _, response, error in
                    DispatchQueue.main.async {
                        guard self.activeSettingsWindow === window else { return }
                        test.isEnabled = true
                        let reachable = error == nil && (response as? HTTPURLResponse)
                            .map({ (200...399).contains($0.statusCode) }) == true
                        status.stringValue = reachable ? "Server reachable" : "Server unavailable"
                        if reachable && saveServer() {
                            refreshUserDirectory()
                            refreshMonitors()
                            updateState()
                        }
                    }
                }.resume()
            case "user":
                guard let number = users.selectedItem?.representedObject as? String, !number.isEmpty else { return }
                if !self.clientToken.isEmpty && locked {
                    showError("User assignment is locked. Ask your administrator to edit the monitor.")
                    refreshMonitors()
                    return
                }
                if !self.clientToken.isEmpty && number == self.storage.string(forKey: "user_no") { return }
                guard saveServer() else {
                    showError("Enter a valid server IP and port.")
                    refreshMonitors()
                    return
                }
                let changing = !self.clientToken.isEmpty
                let response = self.clientAuthRequest(["action": changing ? "switch_user" : "enroll",
                                                       "user_no": number], authenticated: changing)
                guard response?["success"] as? Bool == true,
                      let token = response?["token"] as? String, token.count == 64,
                      let id = response?["monitor_id"] as? Int, id > 0 else {
                    if response?["code"] as? String == "no_monitors" {
                        pendingUserNo = number
                        monitorSettingsLocked = false
                        picker.removeAllItems()
                        picker.addItem(withTitle: "Add new...")
                        picker.lastItem?.representedObject = -1
                        updateMonitorFields()
                        monitorNote.stringValue = "No devices assigned. Add the first monitor."
                        updateState()
                        return
                    }
                    showError(response?["error"] as? String ?? "Unable to select user.")
                    if changing { refreshMonitors() }
                    else { users.selectItem(at: 0) }
                    updateState()
                    return
                }
                pendingUserNo = nil
                self.clientToken = token
                self.isServerConnected = true
                self.selectedMonitorId = id
                self.keychainSaveToken(token)
                refreshMonitors()
                updateState()
                if !changing { self.startKeyboardMonitoring() }
            case "monitor":
                updateMonitorFields()
                let id = picker.selectedItem?.representedObject as? Int ?? 0
                guard !self.clientToken.isEmpty, id > 0 else { return }
                if id != self.selectedMonitorId {
                    let selected = self.clientAuthRequest(["action":"select", "monitor_id":id],authenticated:true)
                    if selected?["success"] as? Bool != true {
                        showError(selected?["error"] as? String ?? "Monitor selection denied.")
                        refreshMonitors()
                        updateState()
                        return
                    }
                    self.selectedMonitorId = id
                    refreshMonitors()
                    updateState()
                }
                monitorNote.stringValue = "Monitor assigned to this installation."
                updateState()
            case "disconnect":
                guard !locked else { showError("User assignment is locked. Ask your administrator to edit the monitor."); return }
                _ = self.clientAuthRequest(["action":"logout"],authenticated:true)
                let query: [String: Any] = [
                    kSecClass as String:kSecClassGenericPassword,
                    kSecAttrService as String:self.keychainService,
                    kSecAttrAccount as String:self.sessionAccount()
                ]
                SecItemDelete(query as CFDictionary)
                self.clientToken = ""
                self.selectedMonitorId = 0
                self.isServerConnected = false
                pendingUserNo = nil
                refreshMonitors()
                updateState()
            case "lock":
                guard !self.clientToken.isEmpty,
                      picker.selectedItem?.representedObject as? Int == self.selectedMonitorId,
                      !monitorSettingsLocked else { return }
                let warning = NSAlert()
                warning.messageText = "Lock monitor settings?"
                warning.informativeText = "Only an administrator can unlock this monitor from the web portal."
                warning.addButton(withTitle: "Lock Settings")
                warning.addButton(withTitle: "Cancel")
                guard warning.runModal() == .alertFirstButtonReturn else { return }
                let payload: [String: Any] = [
                    "action":"lock_settings",
                    "label":label.stringValue,"remark":remark.stringValue,
                    "os":selectedRadio(osRadios),
                    "machine":selectedRadio(machineRadios).lowercased()
                ]
                let result = self.clientAuthRequest(payload, authenticated:true)
                guard result?["success"] as? Bool == true else {
                    showError(result?["error"] as? String ?? "Could not lock settings.")
                    return
                }
                refreshMonitors()
                updateState()
            case "save":
                if let number = pendingUserNo {
                    guard saveServer() else { showError("Enter a valid server address."); return }
                    let response = self.clientAuthRequest([
                        "action":"bootstrap_monitor","user_no":number,
                        "label":label.stringValue,"remark":remark.stringValue,
                        "os":selectedRadio(osRadios),
                        "machine":selectedRadio(machineRadios).lowercased()
                    ])
                    guard response?["success"] as? Bool == true,
                          let token = response?["token"] as? String, token.count == 64,
                          let id = response?["monitor_id"] as? Int, id > 0 else {
                        showError(response?["error"] as? String ?? "Could not create first monitor.")
                        return
                    }
                    self.clientToken = token
                    self.keychainSaveToken(token)
                    self.selectedMonitorId = id
                    self.storage.set(number, forKey: "user_no")
                    self.isServerConnected = true
                    pendingUserNo = nil
                    refreshMonitors()
                    refreshUserDirectory()
                    updateState()
                    self.startKeyboardMonitoring()
                    return
                }
                guard saveServer(), !self.clientToken.isEmpty,
                      let id = picker.selectedItem?.representedObject as? Int,
                      (id == self.selectedMonitorId || id == -1) else {
                    showError("Select one of your assigned monitors.")
                    return
                }
                if id != -1 && monitorSettingsLocked {
                    showError("Monitor settings are locked. Ask your administrator to unlock them.")
                    return
                }
                var request: [String: Any] = [
                    "action":id == -1 ? "create_monitor" : "save_monitor",
                    "label":label.stringValue,"remark":remark.stringValue,
                    "os":selectedRadio(osRadios),
                    "machine":selectedRadio(machineRadios).lowercased()
                ]
                if id > 0 { request["monitor_id"] = id }
                let response = self.clientAuthRequest(request,authenticated:true)
                guard response?["success"] as? Bool == true else {
                    showError(response?["error"] as? String ?? "Could not save monitor.")
                    return
                }
                if id == -1 {
                    let created = response?["monitor_id"] as? Int ?? 0
                    guard created > 0 else { showError("No monitor ID returned."); return }
                    let selected = self.clientAuthRequest([
                        "action":"select","monitor_id":created
                    ],authenticated:true)
                    guard selected?["success"] as? Bool == true else {
                        showError(selected?["error"] as? String ?? "Monitor created, but selection failed.")
                        refreshMonitors()
                        return
                    }
                    self.selectedMonitorId = created
                }
                self.notificationsEnabled = notifications.state == .on
                refreshMonitors()
                refreshUserDirectory()
                updateState()
            case "close":
                self.notificationsEnabled = notifications.state == .on
                NSApp.stopModal(withCode: .OK)
                window.orderOut(nil)
            default: break
            }
        }
        refreshUserDirectory()
        refreshMonitors()
        updateState()
        window.contentView?.layoutSubtreeIfNeeded()
        scroll.contentView.scroll(to: NSPoint(x: 0, y: 0))
        scroll.reflectScrolledClipView(scroll.contentView)
        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
        NSApp.runModal(for: window)
    }

    private func checkAndLogPermissions() {
        // logMessage("=== Permission Check ===", level: .info)
        
        // Check accessibility permission
        let accessibilityEnabled = isInputMonitoringEnabled()
        // logMessage("Accessibility permission: \(accessibilityEnabled ? "GRANTED" : "DENIED")", level: .info)
        
        // Check if we can access browser history directories
        let username = NSUserName()
        let testPaths = [
            "/Users/\(username)/Library/Safari/History.db",
            "/Users/\(username)/Library/Application Support/Google/Chrome/",
            "/Users/\(username)/Library/Application Support/Firefox/Profiles/"
        ]
        
        for path in testPaths {
            let exists = FileManager.default.fileExists(atPath: path)
            let readable = FileManager.default.isReadableFile(atPath: path)
            // logMessage("Path \(path): exists=\(exists), readable=\(readable)", level: .info)
        }
        
        // Check if we can write to Documents
        let documentsPath = NSHomeDirectory() + "/Documents"
        let writable = FileManager.default.isWritableFile(atPath: documentsPath)
        // logMessage("Documents directory writable: \(writable)", level: .info)
        
        // logMessage("=== End Permission Check ===", level: .info)
    }
    
    func applicationWillTerminate(_ aNotification: Notification) {
        // Clean up the event tap
        if let tap = eventTap {
            CGEvent.tapEnable(tap: tap, enable: false)
            eventTap = nil
        }
        
        timer?.invalidate()
        timer = nil
        
        // Clean up status bar
        statusUpdateTimer?.invalidate()
        statusUpdateTimer = nil
        statusBarItem = nil
        statusMenu = nil
        
        // Clean up logging
        logFlushTimer?.invalidate()
        logFlushTimer = nil
        flushLogBuffer() // Final flush
        
        // Clean up notification observers
        NotificationCenter.default.removeObserver(self)
        NSWorkspace.shared.notificationCenter.removeObserver(self)
        
        // Clean up USB monitoring
        if let port = usbNotificationPort {
            IONotificationPortDestroy(port)
        }
        if usbAddedIterator != 0 {
            IOObjectRelease(usbAddedIterator)
        }
        if usbRemovedIterator != 0 {
            IOObjectRelease(usbRemovedIterator)
        }
    }

    private func requestAccessibilityPermission() {
        // First check if we already have permission without prompting
        if AXIsProcessTrusted() {
            // logMessage("Accessibility permission already granted", level: .info)
            storage.set(true, forKey: "accessibility-was-granted")
            return
        }
        
        // Check if permission was previously granted
        let wasPreviouslyGranted = storage.bool(forKey: "accessibility-was-granted")
        
        // Only prompt once per session to avoid spam, unless permission was revoked
        let promptKey = "accessibility-prompt-shown"
        if storage.bool(forKey: promptKey) && !wasPreviouslyGranted {
            // logMessage("Accessibility prompt already shown this session and permission was never granted, skipping", level: .info)
            return
        }
        
        // logMessage("Requesting accessibility permission...", level: .info)
        
        // Show detailed notification based on previous state
        let notification = UNMutableNotificationContent()
        notification.title = "MonitorClient - Accessibility Permission Required"
        
        if wasPreviouslyGranted {
            // App was previously granted but permission was revoked
            notification.body = """
            Accessibility permission was revoked. To restore monitoring:
            
            1. Open System Preferences > Security & Privacy > Privacy > Accessibility
            2. Find "MonitorClient" in the list
            3. Click the "-" button to remove it
            4. Click the "+" button and add MonitorClient again
            5. Restart the MonitorClient app
            
            This will restore keyboard monitoring functionality.
            """
            logMessage("Accessibility permission was previously granted but has been revoked", level: .warning)
        } else {
            // First time requesting permission
            notification.body = """
            MonitorClient needs accessibility permission to monitor keyboard activity.
            
            To grant permission:
            1. Open System Preferences > Security & Privacy > Privacy > Accessibility
            2. Click the lock icon and enter your password
            3. Click the "+" button and add MonitorClient
            4. Check the box next to MonitorClient
            5. Restart the MonitorClient app
            
            Without this permission, keyboard monitoring will not work.
            """
            // logMessage("First time requesting accessibility permission", level: .info)
        }
        
        notification.sound = .default
        self.deliverNotification(notification)
        
        // Also log the instructions
        // logMessage("Accessibility permission instructions sent via notification", level: .info)
        
        let options = [kAXTrustedCheckOptionPrompt.takeUnretainedValue(): true]
        let trusted = AXIsProcessTrustedWithOptions(options as CFDictionary)
        
        if trusted {
            // logMessage("Accessibility permission granted", level: .info)
            storage.set(true, forKey: "accessibility-was-granted")
            // Clear the prompt flag when permission is granted so we can show it again if revoked
            storage.removeObject(forKey: promptKey)
        } else {
            logMessage("Accessibility permission denied - detailed instructions sent via notification", level: .warning)
            // Mark that we've shown the prompt (only if denied)
            storage.set(true, forKey: promptKey)
        }
    }
    
    private func isInputMonitoringEnabled() -> Bool {
        // Check if we have accessibility permissions
        let trusted = AXIsProcessTrusted()
        
        // Only log when permission status changes to avoid spam
        let lastTrustedState = storage.bool(forKey: "last-accessibility-trusted")
        if trusted != lastTrustedState {
            if trusted {
                // logMessage("Accessibility permission status changed: GRANTED", level: .info)
                storage.set(true, forKey: "accessibility-was-granted")
            } else {
                logMessage("Accessibility permission status changed: DENIED", level: .warning)
                // Don't clear the was-granted flag here, as we want to remember it was previously granted
            }
            storage.set(trusted, forKey: "last-accessibility-trusted")
        }
        
        return trusted
    }

    private func checkServerConnectionChange() {
        updateTrayStatusDot()
        // Check if connection state has changed
        if wasServerConnected && !isServerConnected {
            // Connection was lost
            showServerConnectionNotification(connected: false)
        } else if !wasServerConnected && isServerConnected {
            // Connection was restored
            showServerConnectionNotification(connected: true)
        }
        
        // Update previous state
        wasServerConnected = isServerConnected
    }
    
    private func showServerConnectionNotification(connected: Bool) {
        guard notificationsEnabled else { return }
        let notification = UNMutableNotificationContent()
        notification.title = "MonitorClient"
        
        if connected {
            notification.body = "Server connection restored"
            notification.sound = nil // No sound for reconnection
        } else {
            notification.body = "Server connection lost"
            notification.sound = .default
        }
        
        self.deliverNotification(notification)
        // logMessage("Server connection notification: \(connected ? "restored" : "lost")", level: .info)
    }
    
    func getCurrentDateTimeString() -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        dateFormatter.timeZone = TimeZone(identifier: "Asia/Vladivostok") // Set to VLAT
        return dateFormatter.string(from: Date())
    }

    private func setupUSBMonitoring() {
        // Create notification port
        usbNotificationPort = IONotificationPortCreate(kIOMainPortDefault)
        
        // Add notification port to run loop
        if let port = usbNotificationPort {
            let runLoopSource = IONotificationPortGetRunLoopSource(port).takeUnretainedValue()
            CFRunLoopAddSource(CFRunLoopGetCurrent(), runLoopSource, .commonModes)
        }
        
        // Create matching dictionary for USB devices
        let matchingDict = IOServiceMatching(kIOUSBDeviceClassName)
        
        // Add notification for device addition
        let addedCallback: IOServiceMatchingCallback = { (userData, iterator) in
            let appDelegate = Unmanaged<AppDelegate>.fromOpaque(userData!).takeUnretainedValue()
            appDelegate.handleUSBDeviceAdded(iterator)
        }
        
        // Add notification for device removal
        let removedCallback: IOServiceMatchingCallback = { (userData, iterator) in
            let appDelegate = Unmanaged<AppDelegate>.fromOpaque(userData!).takeUnretainedValue()
            appDelegate.handleUSBDeviceRemoved(iterator)
        }
        
        // Register for device addition notifications
        IOServiceAddMatchingNotification(
            usbNotificationPort,
            kIOMatchedNotification,
            matchingDict,
            addedCallback,
            Unmanaged.passUnretained(self).toOpaque(),
            &usbAddedIterator
        )
        
        // Register for device removal notifications
        IOServiceAddMatchingNotification(
            usbNotificationPort,
            kIOTerminatedNotification,
            matchingDict,
            removedCallback,
            Unmanaged.passUnretained(self).toOpaque(),
            &usbRemovedIterator
        )
        
        // Handle any existing devices
        handleUSBDeviceAdded(usbAddedIterator)
        handleUSBDeviceRemoved(usbRemovedIterator)
    }
    
    private func handleUSBDeviceAdded(_ iterator: io_iterator_t) {
        var device = IOIteratorNext(iterator)
        while device != 0 {
            if let deviceName = getUSBDeviceName(device) {
                let currentDate = getCurrentDateTimeString()
                usbDeviceLogs.append(USBDeviceLog(
                    date: currentDate, 
                    device_name: deviceName,
                    device_path: "USB Device Path",
                    device_type: "USB Device",
                    action: "Connected"
                ))
                debugPrint("USB Device Connected: \(deviceName)")
            }
            IOObjectRelease(device)
            device = IOIteratorNext(iterator)
        }
    }
    
    private func handleUSBDeviceRemoved(_ iterator: io_iterator_t) {
        var device = IOIteratorNext(iterator)
        while device != 0 {
            if let deviceName = getUSBDeviceName(device) {
                let currentDate = getCurrentDateTimeString()
                usbDeviceLogs.append(USBDeviceLog(
                    date: currentDate, 
                    device_name: deviceName,
                    device_path: "USB Device Path",
                    device_type: "USB Device",
                    action: "Disconnected"
                ))
                debugPrint("USB Device Disconnected: \(deviceName)")
            }
            IOObjectRelease(device)
            device = IOIteratorNext(iterator)
        }
    }
    
    private func getUSBDeviceName(_ device: io_object_t) -> String? {
        var deviceName: String?
        var vendorId: Int?
        
        // Get device properties
        var properties: Unmanaged<CFMutableDictionary>?
        let result = IORegistryEntryCreateCFProperties(device, &properties, kCFAllocatorDefault, 0)
        
        if result == KERN_SUCCESS, let props = properties?.takeRetainedValue() as? [String: Any] {
            // Get USB device name
            if let name = props["USB Product Name"] as? String {
                deviceName = name
            } else if let name = props["USB Vendor Name"] as? String {
                deviceName = name
            }
            
            // Get vendor ID
            if let vid = props["idVendor"] as? Int {
                vendorId = vid
            }
        }
        
        if let name = deviceName {
            if let vid = vendorId {
                return "\(name) (VID: 0x\(String(format: "%04X", vid)))"
            }
            return name
        }
        return nil
    }
    
    @objc func sendUSBLogs() {
        if self.usbDeviceLogs.count > 0 {
            logMonitoringEvent("Sending USB logs", details: "Count: \(usbDeviceLogs.count)")
            do {
                // Send data in chunks
                sendDataInChunks(data: self.usbDeviceLogs, eventType: "USBLog", chunkSize: 500)
                self.usbDeviceLogs.removeAll(keepingCapacity: true) // Optimized memory management
                lastUSBLogSent = Date()
                logSuccess("USB logs sent and cleared", details: "\(usbDeviceLogs.count) events")
            } catch {
                logError("Error converting USB logs to JSON: \(error)", context: "USBLog")
            }
        } else {
            logMessage("No USB logs to send", level: .debug)
        }
    }



    private func sendDataInChunks(data: [Any], eventType: String, chunkSize: Int = 1000) {
        guard !clientToken.isEmpty && selectedMonitorId > 0 else { return }
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Failed to build endpoint for \(eventType)", context: eventType)
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        // logMessage("=== Sending \(eventType) Data ===", level: .info)
        // logMessage("Endpoint: \(urlString)", level: .info)
        // logMessage("Total data items: \(data.count)", level: .info)
        
        // Create chunks properly
        var chunks: [[Any]] = []
        for i in stride(from: 0, to: data.count, by: chunkSize) {
            let endIndex = min(i + chunkSize, data.count)
            let chunk = Array(data[i..<endIndex])
            chunks.append(chunk)
        }
        
        logMonitoringEvent("Sending \(eventType) data", details: "\(chunks.count) chunks of \(chunkSize) items each")
        
        for (index, chunk) in chunks.enumerated() {
            let chunkData = chunk
            
            // Get username from settings
            let username = storage.string(forKey: "username") ?? NSUserName()
            
            // Use the correct field name for each data type (matching Windows format)
            var postData: [String: Any] = [
                "Event": eventType,
                "MonitorId": selectedMonitorId,
                "Version": APP_VERSION,
                "MacAddress": macAddress,
                "Username": username
            ]

            // Convert Swift structs to dictionaries (matching Windows JSON format)
                switch eventType {
                case "BrowserHistory":
                let historyArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let history = item as? BrowserHistoryLog {
                        return [
                            "browser": history.browser,
                            "url": history.url,
                            "title": history.title,
                            "last_visit": history.last_visit,
                            "date": history.date
                        ]
                    }
                    return [:]
                }
                postData["BrowserHistories"] = historyArray
                
                case "KeyLog":
                let keyLogArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let keyLog = item as? KeyLog {
                        return [
                            "date": keyLog.date,
                            "application": keyLog.application,
                            "key": keyLog.key
                        ]
                    }
                    return [:]
                }
                postData["KeyLogs"] = keyLogArray
                
                case "USBLog":
                let usbLogArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let usbLog = item as? USBDeviceLog {
                        return [
                            "date": usbLog.date,
                            "device_name": usbLog.device_name,
                            "device_path": usbLog.device_path,
                            "device_type": usbLog.device_type,
                            "action": usbLog.action
                        ]
                    }
                    return [:]
                }
                postData["USBLogs"] = usbLogArray
                
                default:
                    postData["Data"] = chunkData
                }

                logMessage("\(eventType) chunk \(index + 1)/\(chunks.count) data prepared", level: .debug)
                
                // Convert to JSON
                guard let jsonData = try? JSONSerialization.data(withJSONObject: postData) else {
                    logError("Failed to serialize JSON data for \(eventType) chunk \(index + 1)", context: eventType)
                    continue
                }
                
                // Create request
                var request = URLRequest(url: url)
                request.httpMethod = "POST"
                request.setValue("application/json", forHTTPHeaderField: "Content-Type")
                request.setValue("Bearer \(clientToken)", forHTTPHeaderField: "Authorization")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
                request.httpBody = jsonData
                
                logMessage("Sending \(eventType) chunk \(index + 1)/\(chunks.count) to: \(urlString)", level: .debug)
            logMessage("Request body size: \(jsonData.count) bytes", level: .debug)
            logMessage("Authorization headers redacted", level: .debug)
            
            // Log the actual JSON being sent for debugging
            if let jsonString = String(data: jsonData, encoding: .utf8) {
                logMessage("Request body: \(jsonData.count) bytes (redacted)", level: .debug)
            }
                
                // Send request
                let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                    DispatchQueue.main.async {
                        if let error = error {
                            self?.logError("\(eventType) chunk \(index + 1)/\(chunks.count) network error: \(error)", context: eventType)
                            self?.logError("Error details: \(error.localizedDescription)", context: eventType)
                            self?.isServerConnected = false // Mark as disconnected on network error
                            return
                        }
                        
                        if let httpResponse = response as? HTTPURLResponse {
                            self?.logMessage("\(eventType) chunk \(index + 1)/\(chunks.count) HTTP response: \(httpResponse.statusCode)", level: .debug)
                        self?.logMessage("Response headers: \(httpResponse.allHeaderFields)", level: .debug)
                        
                        if httpResponse.statusCode != 200 {
                            self?.logError("\(eventType) chunk \(index + 1)/\(chunks.count) HTTP error: \(httpResponse.statusCode)", context: eventType)
                            self?.isServerConnected = false
                            return
                        }
                        }
                        
                        if let data = data, let responseString = String(data: data, encoding: .utf8) {
                            self?.logSuccess("\(eventType) chunk \(index + 1)/\(chunks.count) sent successfully", details: "Response: \(responseString)")
                            self?.logMessage("Response data size: \(data.count) bytes", level: .debug)
                            self?.isServerConnected = true
                        } else {
                            self?.logError("No response data received for \(eventType) chunk \(index + 1)/\(chunks.count)", context: eventType)
                            if let data = data {
                                self?.logMessage("Raw response data: \(data)", level: .debug)
                            }
                        }
                    }
                }
                task.resume()
        }
                
        // logMessage("=== End Sending \(eventType) Data ===", level: .info)
    }
    
    // Helper function to generate random string
    private func generateRandomString(length: Int) -> String {
        let characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        let randomString = String((0..<length).map { _ in
            characters.randomElement()!
        })
        return randomString
    }
    

    
    // Helper function to get readable key names
    func getKeyName(keyCode: Int, character: String) -> String {
        // First check for special key codes that should have specific labels
        switch keyCode {
        case 123: return "LEFT" // Left Arrow
        case 124: return "RIGHT" // Right Arrow
        case 125: return "DOWN" // Down Arrow
        case 126: return "UP" // Up Arrow
        case 36: return "ENTER" // Enter
        case 48: return "TAB" // Tab
        case 49: return " " // Space
        case 51: return "BACKSPACE" // Backspace
        case 53: return "ESCAPE" // Escape
        case 76: return "ENTER" // Enter
        case 116: return "PAGE_UP" // Page Up
        case 117: return "DELETE" // Delete
        case 121: return "PAGE_DOWN" // Page Down
        case 115: return "HOME"
        case 119: return "END"
        case 96: return "F5"
        case 97: return "F6"
        case 98: return "F7"
        case 99: return "F3"
        case 100: return "F8"
        case 101: return "F9"
        case 103: return "F11"
        case 105: return "F13"
        case 107: return "F14"
        case 109: return "F10"
        case 111: return "F12"
        case 122: return "F1"
        case 120: return "F2"
        case 118: return "F4"
        default:
            // If character is not empty and printable, use it
            if !character.isEmpty && character.rangeOfCharacter(from: CharacterSet.controlCharacters.inverted) != nil {
                return character
            }
            return "\(keyCode)"
        }
    }
            
    // New method to setup event tap invalidation monitoring
    private func setupEventTapInvalidationMonitoring() {
        // Monitor for system events that might invalidate event taps
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didWakeNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.screensDidWakeNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didLaunchApplicationNotification,
            object: nil
        )
        
        // Additional system events that might affect event taps
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didTerminateApplicationNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didActivateApplicationNotification,
            object: nil
        )
        
        // Monitor for user session changes
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.sessionDidBecomeActiveNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.sessionDidResignActiveNotification,
            object: nil
        )
        
        debugPrint("Event tap invalidation monitoring setup complete")
    }
    
    @objc private func handleSystemEvent(_ notification: Notification) {
        debugPrint("System event detected: \(notification.name.rawValue)")
        // Force check event tap validity after system events
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            self.checkAndReestablishEventTapThrottled()
        }
    }
    
    // Reset accessibility permissions for testing
    @objc func resetAccessibilityPermissions() {
        // logMessage("Resetting accessibility permission flags", level: .info)
        storage.removeObject(forKey: "accessibility-prompt-shown")
        storage.removeObject(forKey: "last-accessibility-trusted")
        storage.removeObject(forKey: "last-skip-log-time")
        // logMessage("Accessibility permission flags reset", level: .info)
    }
    
    // Force request accessibility permission
    @objc func forceRequestAccessibilityPermission() {
        // logMessage("Force requesting accessibility permission", level: .info)
        storage.removeObject(forKey: "accessibility-prompt-shown")
        requestAccessibilityPermission()
    }
    
    // Force check all permissions (for testing)
    @objc func forceCheckAllPermissions() {
        // logMessage("Force checking all permissions", level: .info)
        checkPermissionsOnStartup()
    }
    
    // Grant all permissions (unified function for both accessibility and screen recording)
    @objc func grantAllPermissions() {
        // Check current permission status
        let accessibilityGranted = AXIsProcessTrusted()
        let screenRecordingGranted = isScreenRecordingEnabled()
        
        // Show notification with instructions
        let notification = UNMutableNotificationContent()
        notification.title = "MonitorClient - Grant Permissions"
        
        if !accessibilityGranted && !screenRecordingGranted {
            // Both permissions needed
            notification.body = """
            MonitorClient needs both permissions to work properly:
            
            🔐 ACCESSIBILITY PERMISSION:
            1. Open System Settings > Privacy & Security > Accessibility
            2. Click the lock icon and enter your password
            3. Click the "+" button and add MonitorClient
            4. Check the box next to MonitorClient
            
            📸 SCREEN RECORDING PERMISSION:
            1. Open System Settings > Privacy & Security > Screen & System Audio Recording
            2. Click the "+" button and add MonitorClient
            3. Check the box next to MonitorClient
            
            After granting both permissions, restart the app.
            """
        } else if !accessibilityGranted {
            // Only accessibility needed
            notification.body = """
            MonitorClient needs accessibility permission:
            
            1. Open System Settings > Privacy & Security > Accessibility
            2. Click the lock icon and enter your password
            3. Click the "+" button and add MonitorClient
            4. Check the box next to MonitorClient
            
            After granting permission, restart the app.
            """
        } else if !screenRecordingGranted {
            // Only screen recording needed
            notification.body = """
            MonitorClient needs screen recording permission:
            
            1. Open System Settings > Privacy & Security > Screen & System Audio Recording
            2. Click the "+" button and add MonitorClient
            3. Check the box next to MonitorClient
            
            After granting permission, restart the app.
            """
        } else {
            // Both permissions already granted
            notification.title = "MonitorClient - Permissions Already Granted"
            notification.body = """
            ✅ Accessibility: Granted
            ✅ Screen Recording: Granted
            
            All required permissions are already granted.
            """
        }
        
        notification.sound = .default
        self.deliverNotification(notification)
        
        // Request permissions programmatically
        if !accessibilityGranted {
            requestAccessibilityPermission()
        }
        
        if !screenRecordingGranted {
            requestScreenRecordingPermission()
        }
        
        // Open System Settings to the appropriate section
        if !accessibilityGranted || !screenRecordingGranted {
            DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) {
                if !accessibilityGranted {
                    self.openAccessibilityPreferences()
                } else if !screenRecordingGranted {
                    self.openScreenRecordingPreferences()
                }
            }
        }
    }
    
    // Reset screen recording permissions for testing
    @objc func resetScreenRecordingPermissions() {
        // logMessage("Resetting screen recording permission flags", level: .info)
        storage.removeObject(forKey: "screen-recording-prompt-shown")
        storage.removeObject(forKey: "last-screen-recording-check")
        storage.removeObject(forKey: "last-screen-recording-notification")
        // logMessage("Screen recording permission flags reset", level: .info)
    }
    
    // Reset screen recording permissions using tccutil (more thorough)
    @objc func resetScreenRecordingPermissionsSystem() {
        // logMessage("Resetting screen recording permissions using tccutil", level: .info)
        
        // Get the bundle identifier
        let bundleId = Bundle.main.bundleIdentifier ?? "com.unknown.monitorclient"
        // logMessage("Bundle identifier: \(bundleId)", level: .info)
        
        // Run tccutil command to reset screen recording permissions
        let task = Process()
        task.launchPath = "/usr/bin/tccutil"
        task.arguments = ["reset", "ScreenCapture", bundleId]
        
        do {
            try task.run()
            task.waitUntilExit()
            
            if task.terminationStatus == 0 {
                // logMessage("Successfully reset screen recording permissions using tccutil", level: .info)
                // Also reset our local flags
                resetScreenRecordingPermissions()
            } else {
                logMessage("Failed to reset screen recording permissions using tccutil (exit code: \(task.terminationStatus))", level: .error)
            }
        } catch {
            logMessage("Error running tccutil: \(error.localizedDescription)", level: .error)
        }
    }
    
    // Force request screen recording permission
    @objc func forceRequestScreenRecordingPermission() {
        // logMessage("Force requesting screen recording permission", level: .info)
        storage.removeObject(forKey: "screen-recording-prompt-shown")
        requestScreenRecordingPermission()
    }
    
    // Open System Preferences to Accessibility settings
    @objc func openAccessibilityPreferences() {
        // logMessage("Opening System Settings to Accessibility settings", level: .info)
        
        // Try multiple approaches to open System Settings
        let approaches = [
            // Approach 1: Direct URL scheme (works on macOS 13+)
            { () -> Bool in
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_Accessibility") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                return false
            },
            
            // Approach 2: Try System Settings (macOS 13+)
            { () -> Bool in
                let script = """
                tell application "System Settings"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_Accessibility"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 3: Try System Preferences (older macOS)
            { () -> Bool in
                let script = """
                tell application "System Preferences"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_Accessibility"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 4: Just open System Settings/Preferences
            { () -> Bool in
                // Try System Settings first (macOS 13+)
                if NSWorkspace.shared.open(URL(string: "x-apple.systempreferences:")!) {
                    return true
                }
                
                // Fallback to System Preferences
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                
                return false
            }
        ]
        
        // Try each approach
        for (index, approach) in approaches.enumerated() {
            if approach() {
                // logMessage("Successfully opened System Settings using approach \(index + 1)", level: .info)
                return
            }
        }
        
        // If all approaches fail, provide manual instructions
        logMessage("Failed to open System Settings automatically", level: .warning)
        // logMessage("Please manually open System Settings > Privacy & Security > Accessibility", level: .info)
        // logMessage("Then add this app to the Accessibility list", level: .info)
        
        // Show a user notification with manual instructions
        let notification = UNMutableNotificationContent()
        notification.title = "Manual Accessibility Setup Required"
        notification.body = "Please open System Settings > Privacy & Security > Accessibility and add this app"
        notification.sound = .default
        
        self.deliverNotification(notification)
    }
    
    // Open System Preferences to Screen Recording settings
    @objc func openScreenRecordingPreferences() {
        // logMessage("Opening System Settings to Screen Recording settings", level: .info)
        
        // Try multiple approaches to open System Settings
        let approaches = [
            // Approach 1: Direct URL scheme (works on macOS 13+)
            { () -> Bool in
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_ScreenCapture") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                return false
            },
            
            // Approach 2: Try System Settings (macOS 13+)
            { () -> Bool in
                let script = """
                tell application "System Settings"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_ScreenCapture"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 3: Try System Preferences (older macOS)
            { () -> Bool in
                let script = """
                tell application "System Preferences"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_ScreenCapture"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 4: Just open System Settings/Preferences
            { () -> Bool in
                // Try System Settings first (macOS 13+)
                if NSWorkspace.shared.open(URL(string: "x-apple.systempreferences:")!) {
                    return true
                }
                
                // Fallback to System Preferences
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                
                return false
            }
        ]
        
        // Try each approach
        for (index, approach) in approaches.enumerated() {
            if approach() {
                // logMessage("Successfully opened System Settings using approach \(index + 1)", level: .info)
                return
            }
        }
        
        // If all approaches fail, provide manual instructions
        logMessage("Failed to open System Settings automatically", level: .warning)
        // logMessage("Please manually open System Settings > Privacy & Security > Screen Recording", level: .info)
        // logMessage("Then add this app to the Screen Recording list", level: .info)
        
        // Show a user notification with manual instructions
        let notification = UNMutableNotificationContent()
        notification.title = "Manual Screen Recording Setup Required"
        notification.body = "Please open System Settings > Privacy & Security > Screen Recording and add this app"
        notification.sound = .default
        
        self.deliverNotification(notification)
    }
    
    // Check accessibility permission periodically and request if needed
    private func checkAccessibilityPermissionPeriodically() {
        // Check every 15 minutes (increased from 5 minutes to reduce annoyance)
        let lastCheck = storage.double(forKey: "last-accessibility-check")
        let currentTime = Date().timeIntervalSince1970
        
        if currentTime - lastCheck > 900 { // 15 minutes
            storage.set(currentTime, forKey: "last-accessibility-check")
            
            if !isInputMonitoringEnabled() {
                // Only show notification if we haven't shown it recently
                let lastNotificationTime = storage.double(forKey: "last-accessibility-notification")
                if currentTime - lastNotificationTime > 3600 { // Only show notification once per hour
                    // logMessage("Periodic accessibility check: permission not granted, showing notification...", level: .info)
                    
                    // Show user notification about accessibility permission
                    let notification = UNMutableNotificationContent()
                    notification.title = "MonitorClient Needs Accessibility Permission"
                    notification.body = "Please grant accessibility permission to enable keyboard monitoring"
                    notification.sound = .default
                    notification.categoryIdentifier = "MONITOR_ACCESSIBILITY"
                    
                    
                    self.deliverNotification(notification)
                    
                    storage.set(currentTime, forKey: "last-accessibility-notification")
                }
                
                // Only request permission if it was previously granted (revoked case)
                let wasPreviouslyGranted = storage.bool(forKey: "accessibility-was-granted")
                if wasPreviouslyGranted {
                    requestAccessibilityPermission()
                }
            } else {
                logMessage("Periodic accessibility check: permission granted", level: .debug)
            }
        }
    }
    
    // Check screen recording permission periodically and request if needed
    private func checkScreenRecordingPermissionPeriodically() {
        if #available(macOS 10.15, *) {
            // Check every 15 minutes (same as accessibility)
            let lastCheck = storage.double(forKey: "last-screen-recording-check")
            let currentTime = Date().timeIntervalSince1970
            
            if currentTime - lastCheck > 900 { // 15 minutes
                storage.set(currentTime, forKey: "last-screen-recording-check")
                
                if !CGPreflightScreenCaptureAccess() {
                    // Only show notification if we haven't shown it recently
                    let lastNotificationTime = storage.double(forKey: "last-screen-recording-notification")
                    if currentTime - lastNotificationTime > 3600 { // Only show notification once per hour
                        // logMessage("Periodic screen recording check: permission not granted, showing notification...", level: .info)
                        
                        // Show user notification about screen recording permission
                        let notification = UNMutableNotificationContent()
                        notification.title = "MonitorClient Needs Screen Recording Permission"
                        notification.body = "Please grant screen recording permission to enable screenshot monitoring"
                        notification.sound = .default
                        notification.categoryIdentifier = "MONITOR_SCREEN_RECORDING"
                        
                        
                        self.deliverNotification(notification)
                        
                        storage.set(currentTime, forKey: "last-screen-recording-notification")
                    }
                    
                    // Only request permission if it was previously granted (revoked case)
                    let wasPreviouslyGranted = storage.bool(forKey: "screen-recording-was-granted")
                    if wasPreviouslyGranted {
                        requestScreenRecordingPermission()
                    }
                } else {
                    logMessage("Periodic screen recording check: permission granted", level: .debug)
                }
            }
        }
    }
    
    

    
    // MARK: - UNUserNotificationCenterDelegate

    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                didReceive response: UNNotificationResponse,
                                withCompletionHandler completionHandler: @escaping () -> Void) {
        switch response.actionIdentifier {
        case "OPEN_ACCESSIBILITY_SETTINGS":
            DispatchQueue.main.async { [weak self] in self?.openAccessibilityPreferences() }
        case "OPEN_SCREEN_RECORDING_SETTINGS":
            DispatchQueue.main.async { [weak self] in self?.openScreenRecordingPreferences() }
        default:
            break
        }
        completionHandler()
    }

    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                willPresent notification: UNNotification,
                                withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void) {
        completionHandler(self.notificationsEnabled ? [.banner, .sound, .list] : [])
    }

    // Debug method to check accessibility permission status
    @objc func debugAccessibilityStatus() {
        // logMessage("=== Accessibility Permission Debug ===", level: .info)
        
        // Check current permission status
        let isTrusted = AXIsProcessTrusted()
        // logMessage("AXIsProcessTrusted(): \(isTrusted)", level: .info)
        
        // Check app bundle identifier
        if let bundleId = Bundle.main.bundleIdentifier {
            // logMessage("Bundle Identifier: \(bundleId)", level: .info)
        }
        
        // Check if app is running from Xcode
        let isRunningFromXcode = ProcessInfo.processInfo.environment["XPC_SERVICE_NAME"] != nil || 
                                ProcessInfo.processInfo.environment["XCODE_RUNNING_FOR_PREVIEWS"] == "1"
        // logMessage("Running from Xcode: \(isRunningFromXcode)", level: .info)
        
        // Check app path
        let appPath = Bundle.main.bundlePath
        // logMessage("App Path: \(appPath)", level: .info)
        
        // Check if app is in Applications folder
        let isInApplications = appPath.contains("/Applications/")
        // logMessage("In Applications folder: \(isInApplications)", level: .info)
        
        // Check stored permission flags
        let promptShown = storage.bool(forKey: "accessibility-prompt-shown")
        let lastTrusted = storage.bool(forKey: "last-accessibility-trusted")
        // logMessage("Stored flags - Prompt shown: \(promptShown), Last trusted: \(lastTrusted)", level: .info)
        
        // Check event tap status
        if let tap = eventTap {
            let isEnabled = CGEvent.tapIsEnabled(tap: tap)
            let portValid = CFMachPortIsValid(tap)
            // logMessage("Event tap - Enabled: \(isEnabled), Port valid: \(portValid)", level: .info)
        } else {
            // logMessage("Event tap: nil", level: .info)
        }
        
        // logMessage("=== End Debug ===", level: .info)
    }
    
    
    
    
    // Check app entitlements and provide debugging guidance
    @objc func checkAppEntitlements() {
        // logMessage("=== App Entitlements Check ===", level: .info)
        
        // Check if app has accessibility entitlements
        let hasAccessibilityEntitlement = Bundle.main.object(forInfoDictionaryKey: "NSAppleEventsUsageDescription") != nil ||
                                         Bundle.main.object(forInfoDictionaryKey: "NSSystemAdministrationUsageDescription") != nil
        
        // logMessage("Has accessibility entitlements: \(hasAccessibilityEntitlement)", level: .info)
        
        // Check if running in sandbox
        let isSandboxed = Bundle.main.object(forInfoDictionaryKey: "com.apple.security.app-sandbox") != nil
        // logMessage("App is sandboxed: \(isSandboxed)", level: .info)
        
        // Check if running from Xcode
        let isRunningFromXcode = ProcessInfo.processInfo.environment["XPC_SERVICE_NAME"] != nil || 
                                ProcessInfo.processInfo.environment["XCODE_RUNNING_FOR_PREVIEWS"] == "1"
        // logMessage("Running from Xcode: \(isRunningFromXcode)", level: .info)
        
        // Provide guidance based on the situation
        if isRunningFromXcode {
            logMessage("⚠️  Running from Xcode - accessibility permissions may not work properly", level: .warning)
            // logMessage("💡 Try building and running the app outside of Xcode", level: .info)
        }
        
        if isSandboxed {
            logMessage("⚠️  App is sandboxed - this may affect accessibility permissions", level: .warning)
        }
        
        if !hasAccessibilityEntitlement {
            logMessage("⚠️  App may be missing accessibility entitlements", level: .warning)
            // logMessage("💡 Check your app's entitlements file", level: .info)
        }
        
        // logMessage("=== End Entitlements Check ===", level: .info)
    }
    
    
    // Manual send all logs function
    @objc func sendAllLogs() {
        // logMessage("=== Manual Send All Logs ===", level: .info)
        
        // Send all types of logs
        DispatchQueue.global(qos: .background).async {
            // 1. Send Tic event (heartbeat)
            do {
                try self.sendTicEvent()
                // self.logMessage("✅ Tic event sent", level: .info)
            } catch {
                self.logError("❌ Tic event failed: \(error)", context: "ManualSend")
            }
            
            // 2. Send browser histories
            self.sendBrowserHistories()
            // self.logMessage("✅ Browser histories sent", level: .info)
            
            // 3. Send key logs
            self.sendKeyLogs()
            // self.logMessage("✅ Key logs sent", level: .info)
            
            // 4. Send USB logs
            do {
                try self.sendUSBLogs()
                // self.logMessage("✅ USB logs sent", level: .info)
            } catch {
                self.logError("❌ USB logs failed: \(error)", context: "ManualSend")
            }
            
            // 5. Send screenshot
            if self.isScreenRecordingEnabled() {
                self.TakeScreenShotsAndPost()
                // self.logMessage("✅ Screenshot sent", level: .info)
            } else {
                self.logMessage("⚠️ Screenshot skipped - permission not granted", level: .warning)
            }
            
            // self.logMessage("=== All Logs Send Complete ===", level: .info)
            
            // Show notification
            DispatchQueue.main.async {
                let notification = UNMutableNotificationContent()
                notification.title = "MonitorClient - Manual Send Complete"
                notification.body = "All monitoring data has been sent to server"
                notification.sound = nil
                self.deliverNotification(notification)
            }
        }
    }
    
    // MARK: - Status Bar Menu
    
    private func setupStatusBarMenu() {
        // Setting up status bar menu
        
        // Create status bar item
        statusBarItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        
        if let button = statusBarItem?.button {
            button.title = ""
            button.image = offlineTrayIcon
            button.imagePosition = .imageOnly
            
            // Add click action for status bar icon
            button.action = #selector(statusBarIconClicked)
            button.target = self
            // Status bar button configured
        } else {
            // Error: Could not get status bar button
        }
        
        // Create menu
        statusMenu = NSMenu()
        statusMenu?.autoenablesItems = false
        
        // Add menu items
        setupStatusMenuItems()
        
        // Set the menu
        statusBarItem?.menu = statusMenu
        updateTrayStatusDot()
        
        // Start status update timer (optimized interval)
        statusUpdateTimer = Timer.scheduledTimer(timeInterval: 10.0, target: self, selector: #selector(updateStatusMenu), userInfo: nil, repeats: true)
        
        // logMessage("Status bar menu setup complete", level: .info)
    }
    
    private func setupStatusMenuItems() {
        guard let menu = statusMenu else { return }
        
        // Clear existing items
        menu.removeAllItems()
        
        // App title
        let titleItem = NSMenuItem(title: "MonitorClient v\(APP_VERSION)", action: nil, keyEquivalent: "")
        titleItem.isEnabled = false
        menu.addItem(titleItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Client information
        let clientItem = NSMenuItem(title: "Client: \(clientIPAddress) / \(macAddress)", action: nil, keyEquivalent: "")
        clientItem.tag = 99
        clientItem.isEnabled = false
        menu.addItem(clientItem)
        
        // Server information
        let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        let serverAddress = serverIP == "Not configured" ? "Not configured" : "\(serverIP):\(serverPort)"
        let serverItem = NSMenuItem(title: "Server: \(serverAddress)", action: nil, keyEquivalent: "")
        serverItem.tag = 100 // Tag for easy identification
        serverItem.isEnabled = false
        menu.addItem(serverItem)
        
        // Accessibility status
        let accessibilityItem = NSMenuItem(title: "Accessibility: Checking...", action: nil, keyEquivalent: "")
        accessibilityItem.tag = 101
        accessibilityItem.isEnabled = false
        menu.addItem(accessibilityItem)
        
        // Screen recording status
        let screenRecordingItem = NSMenuItem(title: "Screen Recording: Checking...", action: nil, keyEquivalent: "")
        screenRecordingItem.tag = 103
        screenRecordingItem.isEnabled = false
        menu.addItem(screenRecordingItem)
        
        // Keyboard monitoring status
        let keyboardItem = NSMenuItem(title: "Keyboard: Checking...", action: nil, keyEquivalent: "")
        keyboardItem.tag = 102
        keyboardItem.isEnabled = false
        menu.addItem(keyboardItem)
        
        // Last sent times section
        menu.addItem(NSMenuItem.separator())
        
        // Key logs last sent
        let keyLogSentItem = NSMenuItem(title: "Key Logs: Never sent", action: nil, keyEquivalent: "")
        keyLogSentItem.tag = 200
        keyLogSentItem.isEnabled = false
        menu.addItem(keyLogSentItem)
        
        // Browser history last sent
        let browserHistorySentItem = NSMenuItem(title: "Browser History: Never sent", action: nil, keyEquivalent: "")
        browserHistorySentItem.tag = 201
        browserHistorySentItem.isEnabled = false
        menu.addItem(browserHistorySentItem)
        
        // USB logs last sent
        let usbLogSentItem = NSMenuItem(title: "USB Logs: Never sent", action: nil, keyEquivalent: "")
        usbLogSentItem.tag = 202
        usbLogSentItem.isEnabled = false
        menu.addItem(usbLogSentItem)
        
        // Screenshots last sent
        let screenshotSentItem = NSMenuItem(title: "Screenshots: Never sent", action: nil, keyEquivalent: "")
        screenshotSentItem.tag = 203
        screenshotSentItem.isEnabled = false
        menu.addItem(screenshotSentItem)
        
        // Tic events last sent
        let ticSentItem = NSMenuItem(title: "Tic Events: Never sent", action: nil, keyEquivalent: "")
        ticSentItem.tag = 204
        ticSentItem.isEnabled = false
        menu.addItem(ticSentItem)
        

        
        // Settings
        let settingsItem = NSMenuItem(title: "Settings...", action: #selector(openSettingsDialog), keyEquivalent: ",")
        settingsItem.keyEquivalentModifierMask = [.command]
        menu.addItem(settingsItem)
        
        // Configuration section
        menu.addItem(NSMenuItem.separator())
        
        // Configuration refresh
        let configRefreshItem = NSMenuItem(title: "Refresh Configuration", action: #selector(manualConfigRefresh), keyEquivalent: "r")
        configRefreshItem.keyEquivalentModifierMask = [.command]
        menu.addItem(configRefreshItem)
        
        // Configuration display
        let configDisplayItem = NSMenuItem(title: "Configuration: Loading...", action: nil, keyEquivalent: "")
        configDisplayItem.tag = 300
        configDisplayItem.isEnabled = false
        menu.addItem(configDisplayItem)
        
        // Grant permissions (unified button for both accessibility and screen recording)
        let grantPermissionsItem = NSMenuItem(title: "Grant Permissions", action: #selector(grantAllPermissions), keyEquivalent: "")
        menu.addItem(grantPermissionsItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Manual test actions
        let sendAllItem = NSMenuItem(title: "Send All Logs Now", action: #selector(sendAllLogs), keyEquivalent: "")
        menu.addItem(sendAllItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Quit
        let quitItem = NSMenuItem(title: "Quit MonitorClient", action: #selector(quitApp), keyEquivalent: "q")
        quitItem.keyEquivalentModifierMask = [.command]
        menu.addItem(quitItem)
    }
    
    @objc private func statusBarIconClicked() {
        updateTrayStatusDot()
        // Show a quick status notification
        let notification = UNMutableNotificationContent()
        notification.title = "MonitorClient Status"
        
        let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        let serverStatus: String
        if serverIP == "Not configured" {
            serverStatus = "Not configured"
        } else {
            let serverAddress = "\(serverIP):\(serverPort)"
            serverStatus = "\(serverAddress) (\(isServerConnected ? "Connected" : "Disconnected"))"
        }
        
        let isAccessibilityEnabled = isInputMonitoringEnabled()
        let isScreenRecordingEnabled = isScreenRecordingEnabled()
        let isKeyboardActive = eventTap != nil && CGEvent.tapIsEnabled(tap: eventTap!)
        
        notification.body = """
        Client: \(clientIPAddress) / \(macAddress)
        Server: \(serverIP) (\(serverStatus))
        Accessibility: \(isAccessibilityEnabled ? "✅" : "❌")
        Screen Recording: \(isScreenRecordingEnabled ? "✅" : "❌")
        Keyboard: \(isKeyboardActive ? "✅" : "❌")
        Keys: \(keyLogs.count)
        USB Events: \(usbDeviceLogs.count)
        """
        
        notification.sound = nil // No sound for status clicks
        self.deliverNotification(notification)
    }
    
    @objc private func updateStatusMenu() {
        updateTrayStatusDot()
        guard let menu = statusMenu else { return }
        
        // Update client information
        if let clientItem = menu.item(withTag: 99) {
            clientItem.title = "Client: \(clientIPAddress) / \(macAddress)"
        }
        
        // Update server status
        if let serverItem = menu.item(withTag: 100) {
            let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
            let serverPort = storage.string(forKey: "server-port") ?? "8924"
            if serverIP == "Not configured" {
                serverItem.title = "Server: Not configured ❌"
            } else {
                let serverAddress = "\(serverIP):\(serverPort)"
                serverItem.title = "Server: \(serverAddress) \(isServerConnected ? "✅" : "❌")"
            }
        }
        
        // Update accessibility status
        if let accessibilityItem = menu.item(withTag: 101) {
            let isEnabled = isInputMonitoringEnabled()
            accessibilityItem.title = "Accessibility: \(isEnabled ? "✅ Enabled" : "❌ Disabled")"
        }
        
        // Update screen recording status
        if let screenRecordingItem = menu.item(withTag: 103) {
            let isEnabled = isScreenRecordingEnabled()
            screenRecordingItem.title = "Screen Recording: \(isEnabled ? "✅ Enabled" : "❌ Disabled")"
        }
        
        // Update keyboard monitoring status
        if let keyboardItem = menu.item(withTag: 102) {
            let isEnabled = eventTap != nil && CGEvent.tapIsEnabled(tap: eventTap!)
            let keyCount = keyLogs.count
            keyboardItem.title = "Keyboard: \(isEnabled ? "✅ Active" : "❌ Inactive") (\(keyCount) keys)"
        }
        
        // Update configuration display
        if let configItem = menu.item(withTag: 300) {
            if let config = currentConfig, let lastUpdate = lastConfigUpdate {
                let formatter = DateFormatter()
                formatter.dateStyle = .short
                formatter.timeStyle = .short
                let lastUpdateStr = formatter.string(from: lastUpdate)
                
                configItem.title = "Configuration - Updated: \(lastUpdateStr)"
            } else {
                configItem.title = "Configuration: Not loaded"
            }
        }
        
        // Update last sent times
        updateLastSentTime(menu: menu, tag: 200, lastSent: lastKeyLogSent, type: "Key Logs")
        updateLastSentTime(menu: menu, tag: 201, lastSent: lastBrowserHistorySent, type: "Browser History")
        updateLastSentTime(menu: menu, tag: 202, lastSent: lastUSBLogSent, type: "USB Logs")
        updateLastSentTime(menu: menu, tag: 203, lastSent: lastScreenshotSent, type: "Screenshots")
        updateLastSentTime(menu: menu, tag: 204, lastSent: lastTicSent, type: "Tic Events")
    }
    
    private func updateLastSentTime(menu: NSMenu, tag: Int, lastSent: Date?, type: String) {
        if let item = menu.item(withTag: tag) {
            if let lastSent = lastSent {
                let formatter = DateFormatter()
                formatter.dateFormat = "HH:mm:ss"
                let timeString = formatter.string(from: lastSent)
                item.title = "\(type): \(timeString)"
            } else {
                item.title = "\(type): Never sent"
            }
        }
    }
    
    // Quit the app (useful for background apps)
    @objc func quitApp() {
        // logMessage("Quitting MonitorClient...", level: .info)
        NSApplication.shared.terminate(nil)
    }
    
    
    private func testFormat(url: URL, data: [String: Any], name: String) {
        // logMessage("Testing format: \(name)", level: .info)
        
        do {
            let jsonData = try JSONSerialization.data(withJSONObject: data)
            
            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
            request.httpBody = jsonData
            
            let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                DispatchQueue.main.async {
                    if let error = error {
                        self?.logError("\(name) failed: \(error)", context: "FormatTest")
                        return
                    }
                    
                    if let httpResponse = response as? HTTPURLResponse {
                        if httpResponse.statusCode == 200 {
                            self?.logSuccess("\(name) successful", details: "HTTP \(httpResponse.statusCode)")
                        } else {
                            self?.logError("\(name) failed - HTTP \(httpResponse.statusCode)", context: "FormatTest")
                        }
                    }
                    
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        self?.logMessage("\(name) response: \(responseString)", level: .debug)
                    }
                }
            }
            task.resume()
            
        } catch {
            logError("Failed to test \(name): \(error)", context: "FormatTest")
        }
    }
}



