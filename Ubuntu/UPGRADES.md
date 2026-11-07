# Ubuntu v1.5.x package upgrades

A Debian package can replace /opt/monitorclient/MonitorClient on disk while
the desktop still runs the *old executable inode*. Merely changing the .deb
does not update an already-running Qt process.

## Canonical release process

Use the Debian package, not the legacy source installer, for production
deployments. Bump the PATCH version on **every new release**; 1.5.1 is only
an example and must be replaced with a version newer than the one currently
published/installed.

```bash
cd Ubuntu
./create_deb_package.sh --version 1.5.1
dpkg-deb -f monitorclient_1.5.1_amd64.deb Package Version Architecture
dpkg-deb -c monitorclient_1.5.1_amd64.deb | grep -E 'MonitorClient$|upgrade-restart|VERSION'
```

This builds the native client with `MONITORCLIENT_VERSION` and compares
`build/MonitorClient --version` with the .deb metadata before publishing.
The self-hosted manifest now refuses to replace an already-published release
with different bytes under the same version. Use `1.5.2`, `1.5.3`, etc.,
as needed rather than overwriting an existing `1.5.1` release.

## Install or upgrade on the desktop machine

```bash
sudo apt install ./monitorclient_1.5.1_amd64.deb
dpkg-query -W -f='${Package} ${Version}\n' monitorclient
/opt/monitorclient/MonitorClient --version
cat /opt/monitorclient/VERSION
```

`apt install ./file.deb` resolves dependencies; `sudo dpkg -i file.deb`
also works if dependencies are already present. The package preserves the
machine default settings as a Debian conffile. Active user configuration
under `~/.config/monitorclient/` and secure tokens in the desktop keyring are
not removed.

At `postinst configure`, `upgrade-restart.sh` scans only *verified*
MonitorClient processes running the older `/opt/monitorclient/MonitorClient`
inode. It sends a graceful termination signal, waits for the old process to
release its single-instance lock, then launches the newly installed binary
as the **same unprivileged desktop user**, with that user's GUI/session
environment. For a user systemd service with its own restart policy, the
service manages its restart. A stuck process is not forcibly killed or
duplicated. Processes launched from other directories are never targeted.

The in-app Settings > Update button now leaves the current client running
through authorization. If authorization or installation fails, it remains
running on its previous version; only a successful package configuration
triggers the package restart helper.

## Check the running build, not just the package version

```bash
pgrep -af MonitorClient
for pid in $(pgrep -x MonitorClient); do
    echo "PID $pid"
    sudo readlink "/proc/$pid/exe"
    sudo stat -Lc 'Running inode %d:%i' "/proc/$pid/exe"
done
stat -Lc 'Installed inode %d:%i' /opt/monitorclient/MonitorClient
/opt/monitorclient/MonitorClient --version
```

An older process frequently shows
`/opt/monitorclient/MonitorClient (deleted)`; its inode differs from the
newly installed file. The helper restarts only this verified stale binary.

If a process remains on a *different* path (for example a source build or
an unrelated manually installed copy), stop that copy deliberately and launch
the package-managed desktop shortcut. Don't use indiscriminate
`pkill -f MonitorClient` commands: they can kill unrelated processes.
Also ensure that only one automatic startup method is active. This release
provides desktop autostart under `/etc/xdg/autostart/`. If a legacy
`monitorclient.service` is starting the app without a working Wayland/X11
session, disable that unit from the logged-in desktop user and use desktop
autostart instead. The package deliberately does not change arbitrary
existing user-service enablement.

An **older in-app updater** used to quit immediately after opening the
privilege prompt, even if the user cancelled. In that specific first-upgrade
case, the new package can be installed while no client process is running.
Launch MonitorClient once from the desktop application launcher. Future
updates made with the new updater preserve the active process until a
successful install, allowing automatic restart.

## Verification

```bash
bash Ubuntu/tests/check-upgrade-scripts.sh
php Backend/tests/ubuntu_upgrade_contract.php
python3 scripts/test_publish_update.py
php Backend/tests/ubuntu_tray_lifecycle_contract.php
```

On an actual Ubuntu X11/Wayland desktop: install a prior package, sign in,
confirm a single process and tray icon, upgrade to a newer version, and
verify that the old PID exits and a new executable inode runs without
losing the account or monitor assignment. Repeat with a cancelled pkexec
prompt, a failed installation, and a second install of the identical
package. Verify only one active monitoring process per desktop user and no
duplicate screenshots. The above tests do not replace native Qt builds and
end-to-end desktop verification. No GitHub Actions are required.
