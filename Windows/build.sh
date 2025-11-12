#!/bin/bash

# Build script for MonitorClient using CMake and MSYS2 MinGW64
# Usage: ./build.sh

set -e  # Exit on error

echo "========================================="
echo "MonitorClient Build Script"
echo "========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "CMakeLists.txt" ]; then
    echo "Error: CMakeLists.txt not found!"
    echo "Please run this script from the Windows directory."
    exit 1
fi

if [ ! -f "MonitorClient.cpp" ]; then
    echo "Error: MonitorClient.cpp not found!"
    echo "Please ensure MonitorClient.cpp is in the current directory."
    exit 1
fi

# Create build directory
echo "Creating build directory..."
mkdir -p build
cd build

# Configure with CMake
echo ""
echo "Configuring with CMake..."
cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release

# Build the project
echo ""
echo "Building the project..."
cmake --build . --config Release

# Check if build was successful
if [ -f "MonitorClient.exe" ]; then
    echo ""
    echo "========================================="
    echo "Build successful!"
    echo "========================================="
    echo "Executable: $(pwd)/MonitorClient.exe"
    echo ""
    echo "Note: You may need to copy DLL files to run the executable."
    echo "Run the following commands to copy required DLLs:"
    echo ""
    echo "  cp /mingw64/bin/libgcc_s_seh-1.dll ."
    echo "  cp /mingw64/bin/libstdc++-6.dll ."
    echo "  cp /mingw64/bin/libwinpthread-1.dll ."
    echo "  cp /mingw64/bin/libsqlite3-0.dll ."
    echo "  cp /mingw64/bin/libjsoncpp-26.dll ."
    echo ""
else
    echo ""
    echo "========================================="
    echo "Build failed!"
    echo "========================================="
    exit 1
fi

