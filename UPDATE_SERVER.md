# MonitorClient self-hosted updates (v1.5+)

The update server is **only a static file server**. It does not need PHP, Flask,
a database, or an API. On the server machine:

```bash
cd /path/to/monitoring
python3 -m http.server 8765 --bind 0.0.0.0 --directory release-updates
```

Use the server LAN address in each desktop client's **Settings → Updates →
Update server**, e.g. `http://192.168.2.241:8765`. Click **Check new version**.
The **Update** button becomes available only for a newer version matching that
client's OS/CPU.

**Security:** The HTTP option is for a trusted private LAN only. SHA-256 verifies
that the bytes match the manifest, but when both the manifest and the file are
served over unauthenticated HTTP, it does *not* prove publisher identity or
protect against a compromised server. For Internet or untrusted use, serve these
files over authenticated HTTPS and sign/notarize the native release packages.
Do not expose a writable release directory.

## Manifest format and exact release filenames

`scripts/publish_update.py` maintains `release-updates/manifest.json`
atomically. It preserves releases for other OSes, rejects version downgrades and
generates hashes itself. For example:

```json
{
  "schema": 1,
  "platforms": {
    "windows-x64": {
      "version": "1.5.1",
      "file": "MonitorClient-1.5.1-windows-x64.zip",
      "sha256": "<64 lowercase hex digits>",
      "size": 12345678
    },
    "macos-arm64": {
      "version": "1.5.1",
      "file": "MonitorClient-1.5.1-macos-arm64.pkg",
      "sha256": "<64 lowercase hex digits>",
      "size": 12345678
    },
    "macos-x64": {
      "version": "1.5.1",
      "file": "MonitorClient-1.5.1-macos-x64.pkg",
      "sha256": "<64 lowercase hex digits>",
      "size": 12345678
    },
    "ubuntu-amd64": {
      "version": "1.5.1",
      "file": "MonitorClient-1.5.1-ubuntu-amd64.deb",
      "sha256": "<64 lowercase hex digits>",
      "size": 12345678
    }
  }
}
```

The example values above are placeholders, not valid published release hashes.
The actual publisher writes real SHA-256 values and a `generated_at` timestamp.
Only a platform with a published release appears in the generated manifest.

## Build and publish per platform

Version prompts are interactive and require MAJOR.MINOR.PATCH, such as `1.5.1`.
The chosen version is compiled into the binary/bundle and used for comparison;
the previous v1.5 builds are equivalent to 1.5.0.

- **Ubuntu x64:** Run `cd Ubuntu && ./create_deb_package.sh`, or pass
  `./create_deb_package.sh --version 1.5.1`. Requires CMake, Qt5 dev packages,
  `dpkg-deb`, `python3`, and the dependencies in `Ubuntu/CMakeLists.txt`.
  The .deb installs `/opt/monitorclient/MonitorClient` and keyreader.
- **macOS arm64 or Intel:** Run `cd Mac && bash build_sign.sh`. It prompts
  for a version and signing identity, builds for the host architecture, and
  publishes an architecture-tagged .pkg. Sign and notarize packages for broad
  distribution. The installed app reads `CFBundleShortVersionString`.
- **Windows x64:** In PowerShell run
  `powershell -ExecutionPolicy Bypass -File Windows/release.ps1`, or
  `...\release.ps1 -Version 1.5.1`. Requires MSYS2 MinGW64, CMake, JsonCpp,
  SQLite3, PowerShell 5.1 and Python 3 **on the build machine**. The generated
  ZIP contains `MonitorClient.exe`, required DLLs and
  `update-helper.ps1` at its root. **Python is not needed on Windows clients.**

All three builders call `scripts/publish_update.py` to publish their release
into the *same* repository-root `release-updates/` directory, retaining the
other OS entries. Copy the complete directory to whichever LAN machine serves
it. Confirm with `curl http://SERVER:8765/manifest.json` from a client.

## What happens when Update is clicked

- **Ubuntu:** Qt downloads the .deb into the per-user cache, verifies SHA-256
  and size, then invokes `pkexec /usr/bin/dpkg -i`. A polkit authorization
  prompt is expected. The app exits; start it again after installation. You
  may need `sudo apt -f install` if the .deb dependencies are not yet installed.
- **macOS:** The client downloads and verifies the .pkg and opens it in the
  native Installer app. Complete the macOS installer prompts; the running
  application exits so the new version will be used when launched again.
- **Windows:** The bundled PowerShell helper checks the manifest, downloads
  and verifies the ZIP, then asks for UAC consent to replace the old portable
  `MonitorClient.exe` and DLLs **after the running process exits**.
  It backs up the old files, restores them on copy failure, and restarts the
  updated executable. If the app was installed in a protected directory, UAC
  is required. The helper itself is bundled with the app, never downloaded
  and run from the update server.

Updates do not change saved account sessions/settings: Ubuntu stores them in
XDG configuration and a keyring, Windows uses HKCU and Credential Manager,
and macOS uses UserDefaults/Keychain.

**First Ubuntu upgrade caution:** Previously published Debian packages may
contain a legacy `postrm` that removes files even during an upgrade.
The updated release scripts fix this for all future packages, but an existing
*old installed package's* `postrm` still runs when upgrading. Check the old
package's `/var/lib/dpkg/info/monitorclient.postrm` first, and perform the
first legacy-to-new update with a local recovery plan if it contains unconditional
cleanup. Back up any nonstandard configuration stored under `/opt/monitorclient`.
Do not assume an already-installed old package is safe to update unattended.

## Test the publishing contract

```bash
python3 -m unittest scripts/test_publish_update.py -v
```

Perform native compile tests and complete manual OS installation tests on
Ubuntu, Windows and macOS before using this with production endpoints.
No GitHub Actions are needed. The updater never installs an older version.
