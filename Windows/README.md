# MonitorClient for Windows - Setup and Build Guide

## Overview

MonitorClient is a Windows system monitoring client that collects and sends various system activity data to a remote server. It runs in the background, provides a system tray icon for status and control, and supports manual and automatic data reporting.

## Features

- **System Tray Integration**: Tray icon with context menu for quick actions and status display
- **Screenshot Capture**: Automatic screen captures at configurable intervals
- **Browser History Monitoring**: Collects history from Chrome, Firefox, Edge, and Chromium-based browsers
- **Key Logging**: Captures keystrokes with application context
- **USB Device Monitoring**: Tracks USB device connect/disconnect events
- **Real-time Status**: Connection status monitoring with visual indicators
- **Configurable Intervals**: Monitoring intervals are fetched from server configuration

## Prerequisites

### 1. MSYS2 Installation

**Download and Install MSYS2:**
1. Download MSYS2 from [https://www.msys2.org/](https://www.msys2.org/)
2. Install to the default location: `C:\msys64`
3. After installation, open MSYS2 terminal and update the system:
   ```bash
   pacman -Syu
   ```

### 2. Required Packages

The build script will automatically install these packages, but you can also install them manually in MSYS2 terminal:

```bash
pacman -S mingw-w64-x86_64-gcc
pacman -S mingw-w64-x86_64-cmake
pacman -S mingw-w64-x86_64-make
pacman -S mingw-w64-x86_64-sqlite3
pacman -S mingw-w64-x86_64-jsoncpp
pacman -S mingw-w64-x86_64-pkg-config
```

## Building the Application

### Option 1: Using the Build Script (Recommended for New Developers)

1. **Open Command Prompt as Administrator**
2. **Navigate to the Windows folder**
3. **Run the build script:**
   ```cmd
   build.bat
   ```

The build script will:
- Check for MSYS2 installation
- Install required packages automatically
- Configure CMake with MinGW Makefiles
- Build the project
- Copy the executable to the parent directory

### Option 2: Manual Build (For Advanced Users)

1. **Open MSYS2 MinGW 64-bit terminal**
2. **Navigate to the Windows folder**
3. **Create build directory:**
   ```bash
   mkdir build
   cd build
   ```
4. **Configure with CMake:**
   ```bash
   cmake -G "MinGW Makefiles" ..
   ```
5. **Build the project:**
   ```bash
   mingw32-make -j
   ```

### Option 3: Using CMake Build Command

From the build directory:
```bash
cmake --build . --config Release
```

## Build Output

After successful build, you'll find:
- `MonitorClient.exe` - The main executable
- Required DLL files in the build directory

### Configuration

The v1.5 desktop app no longer requires `settings.ini`.

1. Open the single **Settings...** option in the tray menu.
2. Enter the server address and port (HTTP on 8924 or HTTPS on 8925).
3. Select a User No and monitor. Existing monitor details are read-only until **Edit**.
4. **Lock Settings** freezes server configuration, user assignment and monitor
   management; status notifications remain editable. Admin unlocks from the portal.

The server address, port and notification preference are stored under
`HKEY_CURRENT_USER\\Software\\MonitorClient\\v1.5`.
The device session is stored in Windows Credential Manager.
Screenshot, heartbeat, browser, key and USB intervals come from
`/webapi_config.php` on the configured server, not from a local INI file.
Older `settings.ini` files are ignored and can be removed after checking
the saved server address.

## Running the Application

1. **Open tray Settings and configure the server if needed**
2. **Run `MonitorClient.exe`**
3. **The application will start in the system tray**
4. **Right-click the tray icon for options:**
   - **Status**: Shows detailed connection information
   - **Send All Requests Now**: Manually trigger sending all monitored data
   - **Exit**: Close the application

## System Tray Features

- **Visual Status Indicators:**
  - Green icon: Server connected
  - Red icon: Server disconnected
- **Context Menu:**
  - Status information display
  - Manual data transmission trigger
  - Exit option

## Dependencies

The application requires the following libraries:
- **SQLite3**: Browser history database access
- **JsonCpp**: JSON parsing and generation
- **Windows API**: System monitoring and UI
- **GDI+**: Screenshot capture
- **WinINet**: HTTP communication
- **SetupAPI**: USB device monitoring

## Troubleshooting

### Common Issues

1. **MSYS2 not found**
   - Ensure MSYS2 is installed at `C:\msys64`
   - Reinstall MSYS2 if necessary

2. **Build tools not found**
   - Run the build script as Administrator
   - Ensure MSYS2 packages are installed
   - Check that MinGW-w64 is properly installed

3. **Dependencies not found**
   - The build script will install required packages automatically
   - If manual installation is needed, use the pacman commands listed above

4. **CMake configuration fails**
   - Check that all required packages are installed
   - Ensure you're using the correct MSYS2 environment
   - Verify CMake version (3.10 or higher required)

5. **Build fails with linking errors**
   - Ensure all required libraries are installed
   - Check that the libraries are in the correct MinGW paths
   - Verify that the CMake configuration found all dependencies

### Debug Information

The application creates a debug log file (`monitor_debug.log`) with detailed information about:
- Connection attempts
- Data collection activities
- Error messages
- USB device events

### Cleanup

To remove build artifacts and temporary files:
```cmd
clean.bat
```

## File Structure

```
Windows/
├── MonitorClient.cpp          # Main source code
├── CMakeLists.txt            # CMake configuration
├── settings.ini              # Configuration file
├── build.bat                 # Build script
├── clean.bat                 # Cleanup script
├── README.md                 # This file
├── BUILD_README.md           # Detailed build instructions
├── README_MONITORCLIENT.md   # Application documentation
├── README_UPDATED_FEATURES.md # Feature documentation
└── build/                    # Build directory (created during build)
    ├── MonitorClient.exe     # Generated executable
    ├── libsqlite3-0.dll     # SQLite3 library
    ├── libjsoncpp-26.dll    # JsonCpp library
    └── other dependencies   # Additional required DLLs
```

## Development Notes

### For New Developers

1. **Start with the build script**: Use `build.bat` for your first build
2. **Check the logs**: Review `monitor_debug.log` for any issues
3. **Test configuration**: Ensure `settings.ini` is properly configured
4. **Verify dependencies**: Make sure all required libraries are installed

### Build Environment

- **Compiler**: MinGW-w64 GCC
- **Build System**: CMake with MinGW Makefiles
- **Target**: Windows 7 or later
- **Architecture**: x86_64

### Code Structure

The main source file `MonitorClient.cpp` contains:
- System tray integration
- Screenshot capture functionality
- Browser history collection
- Key logging implementation
- USB device monitoring
- Network communication
- Configuration management

## Security Considerations

- The application requires administrative privileges for some monitoring features
- USB device monitoring requires device notification access
- Browser history access requires appropriate permissions
- The application runs in the background and may be flagged by antivirus software
- Screenshots are stored temporarily and automatically deleted after upload

## Support

For issues or questions:
1. Check the debug log file (`monitor_debug.log`)
2. Review the system tray status dialog
3. Verify network connectivity and server configuration
4. Ensure all dependencies are properly installed

## Version Information

- **Current Version**: 1.2
- **Minimum Windows Version**: Windows 7
- **Build System**: CMake 3.10+
- **Compiler**: MinGW-w64 GCC
- **Dependencies**: SQLite3, JsonCpp, Windows API
