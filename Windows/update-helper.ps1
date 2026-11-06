<#
MonitorClient built-in Windows update helper (PowerShell 5.1).
Invoked by the desktop Settings window. Never execute code from manifest.json.
Mode Check: fetch/validate a version record and write a JSON status file.
Mode Stage: download a matching ZIP and verify size + SHA-256.
Mode Apply: after MonitorClient exits, replace only approved portable files, roll
back on failure, and restart. UAC is requested by the client for Apply.
#>
[CmdletBinding()]
param(
    [ValidateSet('Check','Stage','Apply')][string]$Mode = 'Check',
    [string]$BaseUrl = '',
    [string]$CurrentVersion = '1.5',
    [string]$ResultPath = '',
    [string]$PackagePath = '',
    [string]$InstallDir = '',
    [int]$ParentPid = 0
)
$ErrorActionPreference = 'Stop'
$maximumBytes = 536870912
$platform = 'windows-x64'
function Emit-Result([bool]$success, [string]$message, [hashtable]$extra = @{}) {
    $result = @{ success=$success; message=$message }
    foreach ($name in $extra.Keys) { $result[$name] = $extra[$name] }
    $json = $result | ConvertTo-Json -Compress -Depth 5
    if ($ResultPath) {
        [System.IO.File]::WriteAllText($ResultPath,$json,(New-Object System.Text.UTF8Encoding($false)))
    } else { Write-Output $json }
}
function Fetch-File([uri]$uri,[string]$destination,[long]$limit) {
    if (@('http','https') -notcontains $uri.Scheme) { throw 'Updates require HTTP or HTTPS.' }
    $request = [System.Net.HttpWebRequest]::Create($uri)
    $request.AllowAutoRedirect = $false
    $request.Timeout = 20000
    $request.ReadWriteTimeout = 30000
    $response = $request.GetResponse()
    try {
        if ([int]$response.StatusCode -ne 200) { throw "Update server returned HTTP $([int]$response.StatusCode)" }
        if ($response.ContentLength -gt $limit) { throw 'Update exceeds maximum permitted size.' }
        $inStream = $response.GetResponseStream()
        $outStream = [System.IO.File]::Create($destination)
        try {
            $buffer = New-Object byte[] 65536
            [long]$count = 0
            while (($n=$inStream.Read($buffer,0,$buffer.Length)) -gt 0) {
                $count += $n
                if ($count -gt $limit) { throw 'Update exceeds maximum permitted size.' }
                $outStream.Write($buffer,0,$n)
            }
        } finally { $outStream.Dispose(); $inStream.Dispose() }
    } finally { $response.Close() }
}
function Get-Release {
    if ([string]::IsNullOrWhiteSpace($BaseUrl)) { throw 'Enter the update server URL.' }
    $base = [uri]::new($BaseUrl.Trim().TrimEnd('/') + '/')
    if (@('http','https') -notcontains $base.Scheme -or
        $base.UserInfo -ne '' -or $base.Query -ne '' -or $base.Fragment -ne '') {
        throw 'Update URL must be HTTP or HTTPS without credentials or query parameters.'
    }
    $tmp = Join-Path $env:TEMP ("monitor-manifest-" + [guid]::NewGuid().ToString('N') + '.json')
    try {
        Fetch-File ([uri]::new($base,'manifest.json')) $tmp 262144
        $manifest = Get-Content -Raw -LiteralPath $tmp -Encoding UTF8 | ConvertFrom-Json
        if ($manifest.schema -ne 1) { throw 'Unsupported manifest schema.' }
        $entry = $manifest.platforms.'windows-x64'
        if ($null -eq $entry) { return $null }
        $version = [string]$entry.version
        if ($version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+$') { throw 'Invalid version in manifest.' }
        if ($entry.file -cne "MonitorClient-$version-windows-x64.zip") { throw 'Unexpected Windows package name.' }
        if ($entry.sha256 -notmatch '^[a-fA-F0-9]{64}$') { throw 'Invalid SHA-256 field.' }
        [long]$size = $entry.size
        if ($size -le 0 -or $size -gt $maximumBytes) { throw 'Invalid Windows package size.' }
        $url = [uri]::new($base,[string]$entry.file)
        if ($url.Scheme -ne $base.Scheme -or $url.Host -ne $base.Host -or $url.Port -ne $base.Port) {
            throw 'Cross-origin update packages are not allowed.'
        }
        return @{
            version=$version
            file=[string]$entry.file
            sha256=([string]$entry.sha256).ToLowerInvariant()
            size=$size
            url=$url.AbsoluteUri
        }
    } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }
}
try {
    if ($Mode -in @('Check','Stage')) {
        $release = Get-Release
        if ($null -eq $release) {
            Emit-Result $true 'No Windows x64 release published.' @{ available=$false }
            exit 0
        }
        $installed = if ($CurrentVersion -match '^[0-9]+\.[0-9]+(\.[0-9]+)?$') {
            [version]$CurrentVersion
        } else { throw 'Invalid installed version.' }
        if ([version]$release.version -le $installed) {
            Emit-Result $true "Up to date ($CurrentVersion)." @{ available=$false }
            exit 0
        }
        if ($Mode -eq 'Check') {
            Emit-Result $true ("Available: "+$release.version) @{
                available=$true; version=$release.version
            }
            exit 0
        }
        $cache = Join-Path $env:LOCALAPPDATA 'MonitorClient\updates'
        New-Item -ItemType Directory -Force -Path $cache | Out-Null
        $destination = Join-Path $cache $release.file
        $partial = "$destination.download"
        try {
            Fetch-File ([uri]$release.url) $partial $maximumBytes
            if ((Get-Item -LiteralPath $partial).Length -ne $release.size) {
                throw 'Downloaded file size does not match manifest.'
            }
            $sha = (Get-FileHash -LiteralPath $partial -Algorithm SHA256).Hash.ToLowerInvariant()
            if ($sha -ne $release.sha256) { throw 'Downloaded ZIP SHA-256 mismatch.' }
            Move-Item -LiteralPath $partial -Destination $destination -Force
        } finally { Remove-Item -LiteralPath $partial -Force -ErrorAction SilentlyContinue }
        Emit-Result $true ("Verified "+$release.version) @{
            available=$true; version=$release.version; packagePath=$destination
            sha256=$release.sha256
        }
        exit 0
    }
    if ($Mode -eq 'Apply') {
        if ($ParentPid -le 0 -or !$InstallDir -or !$PackagePath) { throw 'Incomplete update installation parameters.' }
        if (!(Test-Path -LiteralPath $PackagePath -PathType Leaf)) { throw 'Update ZIP missing.' }
        $base = [System.IO.Path]::GetFileName($PackagePath)
        if ($base -notmatch '^MonitorClient-[0-9]+\.[0-9]+\.[0-9]+-windows-x64\.zip$') {
            throw 'Invalid update ZIP filename.'
        }
        $temp = Join-Path $env:TEMP ("monitor-install-" + [guid]::NewGuid().ToString('N'))
        $stage = Join-Path $temp 'staged'
        $backup = Join-Path $temp 'backup'
        New-Item -ItemType Directory -Force -Path $stage,$backup | Out-Null
        $archive = [System.IO.Compression.ZipFile]::OpenRead($PackagePath)
        try {
            $names = @($archive.Entries | ForEach-Object { $_.FullName })
            foreach ($name in $names) {
                if ($name -notmatch '^(MonitorClient\.exe|update-helper\.ps1|[A-Za-z0-9_.-]+\.dll)$') {
                    throw "Unexpected file in update ZIP: $name"
                }
            }
            if ($names -cnotcontains 'MonitorClient.exe') { throw 'New MonitorClient.exe is missing.' }
        } finally { $archive.Dispose() }
        Expand-Archive -LiteralPath $PackagePath -DestinationPath $stage -Force
        for ($i=0; $i -lt 120; $i++) {
            if (-not (Get-Process -Id $ParentPid -ErrorAction SilentlyContinue)) { break }
            Start-Sleep -Milliseconds 500
        }
        if (Get-Process -Id $ParentPid -ErrorAction SilentlyContinue) {
            throw 'Previous MonitorClient did not exit; old version kept.'
        }
        $staged = @(Get-ChildItem -LiteralPath $stage -File)
        try {
            foreach ($file in $staged) {
                $target = Join-Path $InstallDir $file.Name
                if (Test-Path -LiteralPath $target) {
                    Copy-Item -LiteralPath $target -Destination (Join-Path $backup $file.Name) -Force
                }
                Copy-Item -LiteralPath $file.FullName -Destination $target -Force
            }
        } catch {
            foreach ($old in @(Get-ChildItem -LiteralPath $backup -File)) {
                Copy-Item -LiteralPath $old.FullName -Destination (Join-Path $InstallDir $old.Name) -Force
            }
            throw
        }
        Start-Process -FilePath (Join-Path $InstallDir 'MonitorClient.exe') -WorkingDirectory $InstallDir
        Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
        exit 0
    }
} catch {
    Emit-Result $false $_.Exception.Message
    exit 1
}
