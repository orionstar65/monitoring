#!/usr/bin/env bash
# Restart only an old, package-managed MonitorClient executable after dpkg
# has installed the new binary. Called by DEBIAN/postinst configure.
#
# This is deliberately narrower than pkill -f MonitorClient: never touch a
# similarly named program, a different installation, or a root-owned session.
# A running executable can keep its old inode after a .deb replacement.
set -u

[[ "${1:-}" == "configure" || "${1:-}" == "--restart-running" ]] || exit 0

target=/opt/monitorclient/MonitorClient
[[ -x "$target" ]] || exit 0
new_inode=$(stat -Lc '%d:%i' -- "$target" 2>/dev/null) || exit 0
if [[ "$EUID" -ne 0 ]]; then
    echo "[MonitorClient upgrade] Restart helper requires root; skipped." >&2
    exit 0
fi

for proc in /proc/[0-9]*; do
    [[ -d "$proc" && -r "$proc/comm" ]] || continue
    IFS= read -r name < "$proc/comm" || continue
    [[ "$name" == MonitorClient ]] || continue

    # /proc/PID/exe reports "(deleted)" when dpkg replaced the running file.
    exe=$(readlink -- "$proc/exe" 2>/dev/null) || continue
    [[ "$exe" == "$target" || "$exe" == "$target (deleted)" ]] || continue
    old_inode=$(stat -Lc '%d:%i' -- "$proc/exe" 2>/dev/null) || continue
    [[ "$old_inode" != "$new_inode" ]] || continue

    pid=${proc##*/}
    uid=$(stat -c '%u' -- "$proc" 2>/dev/null) || continue
    [[ "$uid" =~ ^[0-9]+$ && "$uid" -gt 0 ]] || continue
    passwd_record=$(getent passwd "$uid") || continue
    user_name=${passwd_record%%:*}
    IFS=: read -r _ _ _ _ _ user_home _ <<< "$passwd_record"
    [[ -n "$user_name" && -n "$user_home" ]] || continue

    # Read *only* GUI/session parameters from the already-running app. Never
    # log or forward its entire environment (which might contain credentials).
    session_env=()
    graphical=false
    if [[ -r "$proc/environ" ]]; then
        while IFS= read -r -d '' entry; do
            case "$entry" in
                DISPLAY=?*|WAYLAND_DISPLAY=?*) graphical=true; session_env+=("$entry");;
                XDG_RUNTIME_DIR=?*|DBUS_SESSION_BUS_ADDRESS=?*|XAUTHORITY=?*|XDG_SESSION_TYPE=?*|XDG_CURRENT_DESKTOP=?*|XDG_SESSION_DESKTOP=?*|XDG_CACHE_HOME=?*|XDG_CONFIG_HOME=?*|XDG_DATA_HOME=?*|XDG_STATE_HOME=?*|LANG=?*|LC_ALL=?*) session_env+=("$entry");;
            esac
        done < "$proc/environ"
    fi

    managed_by_service=false
    if [[ -r "$proc/cgroup" ]] && grep -q 'monitorclient.service' "$proc/cgroup"; then
        managed_by_service=true
    fi
    if [[ "$managed_by_service" != true && "$graphical" != true ]]; then
        echo "[MonitorClient upgrade] PID $pid has no GUI session; leave it running for manual restart." >&2
        continue
    fi

    echo "[MonitorClient upgrade] Replacing old MonitorClient PID $pid (UID $uid)." >&2
    kill -TERM "$pid" 2>/dev/null || continue
    # Give Qt time to release its per-user single-instance lock.
    for ((i=0;i<40;i++)); do
        kill -0 "$pid" 2>/dev/null || break
        sleep 0.25
    done
    if kill -0 "$pid" 2>/dev/null; then
        echo "[MonitorClient upgrade] PID $pid did not exit; not launching a duplicate." >&2
        continue
    fi
    if [[ "$managed_by_service" == true ]]; then
        # The existing user unit's Restart=always starts the installed binary.
        echo "[MonitorClient upgrade] User service handles restart for UID $uid." >&2
        continue
    fi
    if ! command -v runuser >/dev/null 2>&1 || ! command -v setsid >/dev/null 2>&1; then
        echo "[MonitorClient upgrade] runuser/setsid unavailable; start via desktop launcher." >&2
        continue
    fi
    # Detach completely from dpkg/pkexec. Restrict the environment to
    # documented desktop variables, and drop privileges before exec.
    (
        cd /opt/monitorclient || exit 0
        exec runuser -u "$user_name" -- /usr/bin/env -i \
            "HOME=$user_home" "USER=$user_name" "LOGNAME=$user_name" \
            "PATH=/usr/local/bin:/usr/bin:/bin" "${session_env[@]}" \
            setsid "$target" </dev/null >/dev/null 2>&1
    ) </dev/null >/dev/null 2>&1 &
    echo "[MonitorClient upgrade] New installed client launch requested for UID $uid." >&2
done

# Never turn a correctly installed package into a dpkg failure just because a
# graphical session was not available. The desktop launcher remains fallback.
exit 0
