<?php
/**
 * FFmpeg Installation Test Script
 * Use this to diagnose FFmpeg installation issues on Windows XAMPP
 */

echo "<h2>FFmpeg Installation Test</h2>\n";
echo "<pre>\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP OS Family: " . PHP_OS_FAMILY . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "Current User: " . get_current_user() . "\n";
echo "System PATH: " . getenv('PATH') . "\n\n";

// Test FFmpeg paths
$ffmpegPaths = [
    // Windows paths (check first for Windows systems)
    'ffmpeg',  // System PATH (Windows)
    'ffmpeg.exe',  // Windows executable
    'C:\\ffmpeg\\bin\\ffmpeg.exe',  // Common Windows installation
    'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',  // Program Files
    'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe',  // Program Files x86
    'D:\\ffmpeg\\bin\\ffmpeg.exe',  // D drive installation
    'D:\\xampp\\bin\\ffmpeg.exe',  // XAMPP bin directory
    'D:\\xampp\\bin\\ffmpeg',  // XAMPP bin directory (no extension)
];

echo "Testing FFmpeg paths:\n";
echo "====================\n";

$foundFFmpeg = false;
foreach ($ffmpegPaths as $path) {
    echo "Testing: {$path} ... ";
    
    // On Windows, try both with and without quotes for paths with spaces
    if (PHP_OS_FAMILY === 'Windows') {
        $testPaths = [$path];
        if (strpos($path, ' ') !== false) {
            $testPaths[] = '"' . $path . '"';
        }
    } else {
        $testPaths = [$path];
    }
    
    $success = false;
    foreach ($testPaths as $testPath) {
        exec("{$testPath} -version 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✓ FOUND!\n";
            echo "  Working path: {$testPath}\n";
            echo "  Version info: " . implode("\n  ", array_slice($output, 0, 3)) . "\n";
            $foundFFmpeg = true;
            $success = true;
            break;
        }
    }
    
    if (!$success) {
        echo "✗ Not found\n";
    }
}

echo "\n";
if ($foundFFmpeg) {
    echo "✓ FFmpeg is properly installed and accessible!\n";
} else {
    echo "⚠ FFmpeg is NOT found. Installation recommendations:\n";
    echo "\n";
    if (PHP_OS_FAMILY === 'Windows') {
        echo "For Windows:\n";
        echo "1. Download FFmpeg from: https://ffmpeg.org/download.html#build-windows\n";
        echo "2. Extract to: C:\\ffmpeg\\\n";
        echo "3. Add C:\\ffmpeg\\bin to your system PATH environment variable\n";
        echo "4. OR copy ffmpeg.exe to: D:\\xampp\\bin\\ffmpeg.exe\n";
        echo "5. Restart Apache after installation\n";
        echo "\n";
        echo "Alternative: Use a package manager like Chocolatey:\n";
        echo "  choco install ffmpeg\n";
    } else {
        echo "For Unix/Linux/macOS:\n";
        echo "1. Install via package manager (brew, apt, yum, etc.)\n";
        echo "2. Or download from: https://ffmpeg.org/download.html\n";
        echo "3. Ensure FFmpeg is in your system PATH\n";
    }
}

echo "</pre>\n";
?>
