# Desktop password sign-in — Monitoring App v1.5

## Configuration flow

On Windows, macOS, and Ubuntu, open **Settings**. Enter the Server IP/hostname and port, then your numeric **User No** and account **Password**. Click **Sign In**. The user directory selector is no longer part of the visible settings workflow.

A successful sign-in creates a device-session token in `client_sessions` with `expires_at = NULL`. It is sent in the Authorization Bearer header, stored only in the operating system's secure credential store, and restored automatically on restart. The password is not stored. The session remains valid until explicitly revoked, the account/password is reset or changed, or an administrator removes access.

Credential stores:
- Windows: Credential Manager
- macOS: Keychain
- Ubuntu: Secret Service via `secret-tool` (requires installed and unlocked keyring)

For Ubuntu, install a working libsecret/Secret Service keyring before expecting automatic session restoration.

**Network security:** Prefer HTTPS on port 8925 with a trusted certificate for password-based sign-in. HTTP port 8924 requires the explicit private-LAN opt-in (`MONITOR_ALLOW_HTTP=1`); passwords transmitted over HTTP are not encrypted.

## Monitor selection

Signing in does not automatically activate another physical machine's monitor. The server returns all monitors mapped to the authenticated account, and the client shows **Add new...**. Choose a monitor and click **Save** to activate it. An administrator's monitor-level lock is enforced server-side. If an existing monitor is locked or in use, an administrator may need to approve or unlock reassignment.

The monitor dropdown shows `IP address · OS-Machine` without exposing internal database IDs in the UI.

## Settings and manual tools

**Save** commits server and monitor edits and notification settings. Closing the dialog does not apply draft notification or monitor changes. **Sign Out** revokes the authenticated session even when monitor settings are locked. Changing the saved server endpoint revokes the prior endpoint's token.

Actions now located in Settings:
- Send All Logs
- Refresh Configuration
- Grant Permissions (macOS only)

Status notifications are configured in Settings. Monitoring remains inactive until an authenticated session has selected a monitor.

## Deployment and validation

```bash
cd /var/monitor
git pull --ff-only origin main
php -l Backend/client_auth.php
php Backend/tests/password_signin_settings_v15_contract.php
sudo systemctl reload apache2
```

Rebuild/reinstall each desktop app to use the new UI. Keep the client version at **v1.5**.

Manual scenarios to test on each OS:
1. Sign in with correct User No/password and verify monitor list; incorrect password must produce a clear error.
2. Select a monitor, close Settings without Save and reopen; selection must not have changed. Repeat with Save and verify it was committed.
3. Restart app and confirm token restores the account and active monitor without password prompt.
4. Sign Out and restart; app must require credentials again.
5. Lock a monitor as admin; normal edits must fail, but Sign Out must still work.
6. Change server address and Save; old token must be revoked and new server must require Sign In.
7. Open Send All Logs and Refresh Configuration from Settings; macOS Grant Permissions should open the existing permission workflow.
8. Verify Settings on narrow/small screens and correct status messages after unreachable server or timeout.

The repository changes are source-level; native builds and full live-database smoke tests must be performed on the target systems.
