# Building MonitorClient with CMake and MSYS2 MinGW64

This guide shows you how to build the MonitorClient application using only CMake and MSYS2 MinGW64 on Windows.

## Prerequisites

### 1. Install MSYS2
- Download MSYS2 from: https://www.msys2.org/
- Install to the default location: `C:\msys64`
- After installation, open **MSYS2 MinGW 64-bit** terminal (not the regular MSYS2 terminal)

### 2. Update MSYS2 (First Time Only)
```bash
pacman -Syu
```
Close and reopen the terminal when prompted, then run again:
```bash
pacman -Syu
```

### 3. Install Required Packages
In the **MSYS2 MinGW 64-bit** terminal, run:
```bash
pacman -S mingw-w64-x86_64-gcc
pacman -S mingw-w64-x86_64-cmake
pacman -S mingw-w64-x86_64-make
pacman -S mingw-w64-x86_64-sqlite3
pacman -S mingw-w64-x86_64-jsoncpp
```

Or install all at once:
```bash
pacman -S mingw-w64-x86_64-gcc mingw-w64-x86_64-cmake mingw-w64-x86_64-make mingw-w64-x86_64-sqlite3 mingw-w64-x86_64-jsoncpp
```

## Building the Application

### Step 1: Open MSYS2 MinGW 64-bit Terminal
- Open **MSYS2 MinGW 64-bit** terminal (important: use this specific terminal, not regular MSYS2)
- Navigate to your project directory:
```bash
cd /d/monitoring/Windows
```
(Adjust the path based on where your project is located. In MSYS2, `D:\monitoring` becomes `/d/monitoring`)

### Step 2: Create Build Directory
```bash
mkdir -p build
cd build
```

### Step 3: Configure with CMake
```bash
cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release
```

If CMake can't find the libraries, you can specify the paths explicitly:
```bash
cmake .. -G "MinGW Makefiles" \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_PREFIX_PATH=/mingw64
```

### Step 4: Build the Project
```bash
cmake --build . --config Release
```

Or use make directly:
```bash
make
```

### Step 5: Find Your Executable
After successful build, you'll find:
- `MonitorClient.exe` in the `build/` directory

## Quick Build Script

You can also create a simple build script. Save this as `build.sh` in the Windows directory:

```bash
#!/bin/bash
mkdir -p build
cd build
cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release
cmake --build . --config Release
echo "Build complete! Executable: build/MonitorClient.exe"
```

Make it executable and run:
```bash
chmod +x build.sh
./build.sh
```

## Troubleshooting

### Issue: CMake can't find SQLite3 or JsonCpp
**Solution:** Verify the libraries are installed:
```bash
ls /mingw64/lib/libsqlite3*
ls /mingw64/lib/libjsoncpp*
```

If they don't exist, reinstall:
```bash
pacman -S mingw-w64-x86_64-sqlite3 mingw-w64-x86_64-jsoncpp
```

### Issue: CMake can't find PkgConfig
**Solution:** The CMakeLists.txt may require PkgConfig. Install it:
```bash
pacman -S mingw-w64-x86_64-pkg-config
```

### Issue: Wrong terminal environment
**Solution:** Make sure you're using **MSYS2 MinGW 64-bit** terminal, not regular MSYS2 terminal. The PATH should include `/mingw64/bin`.

### Issue: DLL files not found when running
**Solution:** Copy required DLLs to the build directory:
```bash
cp /mingw64/bin/libgcc_s_seh-1.dll build/
cp /mingw64/bin/libstdc++-6.dll build/
cp /mingw64/bin/libwinpthread-1.dll build/
cp /mingw64/bin/libsqlite3-0.dll build/
cp /mingw64/bin/libjsoncpp-26.dll build/
```

Or add `/mingw64/bin` to your system PATH.

## File Structure

After building, your structure should look like:
```
Windows/
├── MonitorClient.cpp          # Source file
├── CMakeLists.txt            # CMake configuration
├── build/                    # Build directory
│   └── MonitorClient.exe    # Your executable
└── BUILD_GUIDE.md           # This file
```

## Notes

- Always use **MSYS2 MinGW 64-bit** terminal for building
- The build process creates a Release build by default
- All dependencies are statically linked where possible (libgcc, libstdc++)
- Windows system libraries (wininet, gdiplus, etc.) are linked dynamically

