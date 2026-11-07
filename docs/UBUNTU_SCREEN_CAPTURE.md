# Ubuntu screen capture troubleshooting (MonitorClient v1.5)

MonitorClient needs a real logged-in graphical desktop. A root or system
service cannot capture the logged-in user's Wayland desktop.

## How screenshot capture works

- **GNOME/Wayland:** the regular monitor capture uses xdg-desktop-portal
  ScreenCast + PipeWire and requires the user's screen-selection approval.
  A user pressing **Take Screenshot** or **Send All Logs** can additionally
  request a one-time screenshot through the public Screenshot portal. The
  desktop prompts for permission when necessary.
- **X11:** the client captures the X11 root window first. Older GNOME
  screenshot D-Bus is only a fallback for X11.
- Screen capture and backend upload are separate steps. A capture failure
  means there was no JPEG to upload; a captured screenshot may still have a
  network or backend error.

## Check on the actual Ubuntu client desktop

Run these commands in a terminal **as the same logged-in desktop user who
runs MonitorClient** (not with sudo):

```bash
printf 'User: %s\nType: %s\nDISPLAY: %s\nWAYLAND_DISPLAY: %s\nDBus: %s\n' \
  "$(id -un)" "$XDG_SESSION_TYPE" "$DISPLAY" "$WAYLAND_DISPLAY" "$DBUS_SESSION_BUS_ADDRESS"

systemctl --user --no-pager status \
  xdg-desktop-portal xdg-desktop-portal-gnome pipewire wireplumber

busctl --user --no-pager status org.freedesktop.portal.Desktop
```

If the GNOME portal is not installed, on standard Ubuntu GNOME the packages
are `xdg-desktop-portal`, `xdg-desktop-portal-gnome`, and `pipewire`.
Install missing packages through your package manager, then log out and
back in to activate the desktop session. Do not start MonitorClient using
`sudo` or as a system-level daemon.

After rebuilding and restarting MonitorClient, click **Take Screenshot**
while the graphical session is unlocked. Approve the desktop's screen-share
or screenshot dialog. Then check the app's screenshot notification:

- **Stored on server successfully:** the backend confirmed receipt.
- **Screenshot capture failed** with a permission/session reason: inspect
  the desktop portal services and approved source; no file was uploaded.
- **Screenshot upload failed:** capture worked; inspect the server host,
  port, authentication, and API Request Logs instead.

Automatic Wayland capture requires a persistent, approved ScreenCast stream.
The manual Screenshot portal fallback does **not** silently grant persistent
permission. If the user denied the ScreenCast prompt during startup, restart
MonitorClient from the signed-in desktop session and approve the sharing
prompt. Desktop permission must not be bypassed.

Source-level checks: `php Backend/tests/ubuntu_screenshot_capture_contract.php`.
This is not a replacement for a native Ubuntu build and a live desktop test.
