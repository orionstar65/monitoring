# Windows release builder. Run:
# powershell -ExecutionPolicy Bypass -File Windows/release.ps1
# or .\release.ps1 -Version 1.5.1
[CmdletBinding()]
param([string]$Version)
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $MyInvocation.MyCommand.Path
$repo = Split-Path -Parent $project
if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = Read-Host 'MonitorClient package version (MAJOR.MINOR.PATCH, e.g. 1.5.1)'
}
if ($Version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+$') { throw 'Version must be MAJOR.MINOR.PATCH' }
$msys = @('C:\msys64', "$env:ProgramFiles\msys64", "$env:LOCALAPPDATA\msys64") |
          Where-Object { Test-Path (Join-Path $_ 'mingw64\bin\g++.exe') } |
          Select-Object -First 1
if (-not $msys) { throw 'MSYS2 MinGW64 not found. Install compiler, CMake, SQLite3 and JsonCpp.' }
$bin = Join-Path $msys 'mingw64\bin'
$env:PATH = "$bin;$env:PATH"
$build = Join-Path $project 'build'
$stage = Join-Path $project 'release-stage'
$package = Join-Path $project "MonitorClient-$Version-windows-x64.zip"
Write-Host "Building MonitorClient $Version (x64)..."
& cmake -S $project -B $build -G 'MinGW Makefiles' -DCMAKE_BUILD_TYPE=Release "-DMONITORCLIENT_VERSION=$Version"
if ($LASTEXITCODE -ne 0) { throw 'CMake configure failed' }
& cmake --build $build --config Release -j 4
if ($LASTEXITCODE -ne 0) { throw 'CMake build failed' }
$exe = Join-Path $build 'MonitorClient.exe'
if (-not (Test-Path $exe)) { throw "Missing $exe" }
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null
Copy-Item $exe $stage
$dlls = @('libgcc_s_seh-1.dll','libstdc++-6.dll','libwinpthread-1.dll','libsqlite3-0.dll','libjsoncpp-26.dll')
foreach ($dll in $dlls) {
    $candidateA = Join-Path $build $dll
    $candidateB = Join-Path $bin $dll
    $source = if (Test-Path $candidateA) { $candidateA } elseif (Test-Path $candidateB) { $candidateB } else { $null }
    if (-not $source) { throw "Missing required DLL: $dll" }
    Copy-Item $source $stage
}
if (Test-Path $package) { Remove-Item $package -Force }
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $package -CompressionLevel Optimal
if (-not (Test-Path $package)) { throw 'Archive creation failed' }
$python = Get-Command python -ErrorAction SilentlyContinue
if ($python) {
    & python (Join-Path $repo 'scripts\publish_update.py') --platform windows-x64 --version $Version --package $package --output (Join-Path $repo 'release-updates')
} elseif (Get-Command py -ErrorAction SilentlyContinue) {
    & py -3 (Join-Path $repo 'scripts\publish_update.py') --platform windows-x64 --version $Version --package $package --output (Join-Path $repo 'release-updates')
} else {
    throw 'Python 3 is required on the build machine to generate manifest.json.'
}
if ($LASTEXITCODE -ne 0) { throw 'Update manifest publish failed' }
Write-Host "Release ready: $package"
Write-Host "Serve the release-updates directory with Python HTTP server port 8765"
