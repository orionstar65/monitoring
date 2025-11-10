@echo off
setlocal enabledelayedexpansion

echo ========================================
echo MonitorClient Windows Build Script
echo Using CMake and MSYS2 MinGW64
echo ========================================
echo.

:: Set MSYS2 paths - try multiple common locations
set MSYS2_PATH=
set MINGW64_PATH=

:: Check common MSYS2 installation locations
if exist "C:\msys64" (
    set "MSYS2_PATH=C:\msys64"
    goto :found_msys2
)
if exist "%ProgramFiles%\msys64" (
    set "MSYS2_PATH=%ProgramFiles%\msys64"
    goto :found_msys2
)
set "PROGFILES_X86=%ProgramFiles(x86)%"
if exist "%PROGFILES_X86%\msys64" (
    set "MSYS2_PATH=%PROGFILES_X86%\msys64"
    goto :found_msys2
)
if exist "%LOCALAPPDATA%\msys64" (
    set "MSYS2_PATH=%LOCALAPPDATA%\msys64"
    goto :found_msys2
)

:: MSYS2 not found
echo ERROR: MSYS2 not found in common installation locations.
echo Searched locations:
echo   - C:\msys64
echo   - %ProgramFiles%\msys64
set "PROGFILES_X86=%ProgramFiles(x86)%"
echo   - %PROGFILES_X86%\msys64
echo   - %LOCALAPPDATA%\msys64
echo.
echo Please install MSYS2 from https://www.msys2.org/
echo Recommended installation path: C:\msys64
echo After installation, run this script again.
echo.
pause
exit /b 1

:found_msys2

set MINGW64_PATH=%MSYS2_PATH%\mingw64
set MINGW64_BIN=%MINGW64_PATH%\bin

echo MSYS2 found at: %MSYS2_PATH%
echo MinGW64 path: %MINGW64_PATH%
echo.

:: Add MinGW64 to PATH for this session
set PATH=%MINGW64_BIN%;%PATH%

:: Check if required tools are available
echo Checking build tools...

if not exist "%MINGW64_BIN%\gcc.exe" (
    echo ERROR: GCC not found at %MINGW64_BIN%\gcc.exe
    echo.
    echo Please install required packages in MSYS2 MinGW 64-bit terminal:
    echo   pacman -S mingw-w64-x86_64-gcc
    echo.
    pause
    exit /b 1
)

if not exist "%MINGW64_BIN%\cmake.exe" (
    echo ERROR: CMake not found at %MINGW64_BIN%\cmake.exe
    echo.
    echo Please install required packages in MSYS2 MinGW 64-bit terminal:
    echo   pacman -S mingw-w64-x86_64-cmake
    echo.
    pause
    exit /b 1
)

if not exist "%MINGW64_BIN%\mingw32-make.exe" (
    echo ERROR: Make not found at %MINGW64_BIN%\mingw32-make.exe
    echo.
    echo Please install required packages in MSYS2 MinGW 64-bit terminal:
    echo   pacman -S mingw-w64-x86_64-make
    echo.
    pause
    exit /b 1
)

:: Check for required libraries
if not exist "%MINGW64_PATH%\lib\libsqlite3.a" (
    if not exist "%MINGW64_PATH%\lib\libsqlite3.dll.a" (
        echo WARNING: SQLite3 library not found.
        echo Please install: pacman -S mingw-w64-x86_64-sqlite3
        echo.
    )
)

if not exist "%MINGW64_PATH%\lib\libjsoncpp.a" (
    if not exist "%MINGW64_PATH%\lib\libjsoncpp.dll.a" (
        echo WARNING: JsonCpp library not found.
        echo Please install: pacman -S mingw-w64-x86_64-jsoncpp
        echo.
    )
)

echo Build tools found successfully.
echo.

:: Check if source files exist
if not exist "MonitorClient.cpp" (
    echo ERROR: MonitorClient.cpp not found in current directory.
    echo Please run this script from the Windows directory.
    pause
    exit /b 1
)

if not exist "CMakeLists.txt" (
    echo ERROR: CMakeLists.txt not found in current directory.
    echo Please run this script from the Windows directory.
    pause
    exit /b 1
)

:: Create build directory
if not exist "build" (
    echo Creating build directory...
    mkdir build
) else (
    echo Build directory exists.
)

:: Change to build directory
cd build

:: Configure with CMake
echo.
echo ========================================
echo Configuring with CMake...
echo ========================================
echo.

cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release

if %errorlevel% neq 0 (
    echo.
    echo ERROR: CMake configuration failed.
    echo.
    echo Troubleshooting:
    echo 1. Ensure all required packages are installed:
    echo    pacman -S mingw-w64-x86_64-gcc mingw-w64-x86_64-cmake mingw-w64-x86_64-make mingw-w64-x86_64-sqlite3 mingw-w64-x86_64-jsoncpp
    echo.
    echo 2. Try running CMake manually from MSYS2 MinGW 64-bit terminal:
    echo    cd build
    echo    cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release
    echo.
    pause
    exit /b 1
)

echo.
echo CMake configuration successful.
echo.

:: Build the project
echo ========================================
echo Building MonitorClient...
echo ========================================
echo.

cmake --build . --config Release

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Build failed.
    echo Please check the error messages above.
    echo.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Build completed successfully!
echo ========================================
echo.

:: Check if executable was created
if exist "MonitorClient.exe" (
    echo Executable created: MonitorClient.exe
    echo Location: %CD%\MonitorClient.exe
    echo.
    
    :: Copy executable to parent directory
    copy MonitorClient.exe ..\MonitorClient.exe >nul 2>&1
    if exist "..\MonitorClient.exe" (
        echo Executable copied to: ..\MonitorClient.exe
        echo.
    )
    
    :: Copy required DLL files
    echo Copying required DLL files...
    if exist "%MINGW64_BIN%\libgcc_s_seh-1.dll" (
        copy "%MINGW64_BIN%\libgcc_s_seh-1.dll" . >nul 2>&1
        copy "%MINGW64_BIN%\libgcc_s_seh-1.dll" .. >nul 2>&1
    )
    if exist "%MINGW64_BIN%\libstdc++-6.dll" (
        copy "%MINGW64_BIN%\libstdc++-6.dll" . >nul 2>&1
        copy "%MINGW64_BIN%\libstdc++-6.dll" .. >nul 2>&1
    )
    if exist "%MINGW64_BIN%\libwinpthread-1.dll" (
        copy "%MINGW64_BIN%\libwinpthread-1.dll" . >nul 2>&1
        copy "%MINGW64_BIN%\libwinpthread-1.dll" .. >nul 2>&1
    )
    if exist "%MINGW64_BIN%\libsqlite3-0.dll" (
        copy "%MINGW64_BIN%\libsqlite3-0.dll" . >nul 2>&1
        copy "%MINGW64_BIN%\libsqlite3-0.dll" .. >nul 2>&1
    )
    if exist "%MINGW64_BIN%\libjsoncpp-26.dll" (
        copy "%MINGW64_BIN%\libjsoncpp-26.dll" . >nul 2>&1
        copy "%MINGW64_BIN%\libjsoncpp-26.dll" .. >nul 2>&1
    )
    echo DLL files copied.
    echo.
    
    :: Check for settings.ini
    if not exist "..\settings.ini" (
        echo NOTE: settings.ini not found in parent directory.
        echo The application will use default settings.
        echo You can create settings.ini with:
        echo   [Server]
        echo   ip=192.168.1.45
        echo   port=8924
        echo   username=your_username
        echo.
    )
    
    echo ========================================
    echo Build Summary:
    echo ========================================
    echo - Executable: MonitorClient.exe
    echo - Build Type: Release
    echo - Compiler: MinGW-w64 GCC
    echo - Dependencies: SQLite3, JsonCpp
    echo - Location: %CD%
    echo.
    echo To run the application:
    echo 1. Ensure settings.ini is configured (if needed)
    echo 2. Run: MonitorClient.exe
    echo.
    echo The application will run in the system tray.
    echo Right-click the tray icon for options.
    echo.
    
) else (
    echo ERROR: Executable not found after build.
    echo Please check the build output for errors.
    echo.
)

echo Press any key to exit...
pause >nul
