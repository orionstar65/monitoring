# Web sign-in recovery after retiring usernames (MonitorClient v1.5)

The old web **username** field has been removed from the users table. Web sign-in now accepts **User No or registered email + password**. User No is **not** the database ID. Earlier accounts were initially assigned `users.id + 100000`, although administrators may have edited it later.

## If your existing sign-in says "Invalid login details"

1. Do **not** recreate the administrator account or rerun `Backend/database_setup.sql`; the latter drops the database.
2. On the **backend server**, update your checkout, install the helper as root-owned and list administrator accounts:
   ```bash
   cd /var/monitor
   git pull --ff-only origin main
   sudo install -o root -g root -m 700 Backend/with_database_env.py /usr/local/sbin/monitor-with-db-env
   sudo /usr/local/sbin/monitor-with-db-env php Backend/recover_web_login.php --list-admins
   ```
   This displays only account ID, User No, Full Name, email and whether a recognizable password hash is present. It does not display or change passwords. Use the listed **User No** or email to sign in, **not** the old username or the database ID.
3. If the password is unknown or its hash is unusable, run the **explicit** admin recovery option locally. Enter a new password without placing it on a command line or in the repository:
   ```bash
   sudo /usr/local/sbin/monitor-with-db-env --prompt-recovery-password \
       php Backend/recover_web_login.php --reset-user-no=100001
   ```
   Replace `100001` with the actual User No reported by `--list-admins`. Password must contain at least 12 characters. On success, sign in and change it via the forced password-change form.

   The reset command changes only the chosen administrator's password and `must_change_password` flag. It does not recreate users, monitors or telemetry.

4. If `--list-admins` reports `MISSING` for User No or says the field is absent, the schema migrations may be incomplete. **Back up the database**, then inspect the one-time `Backend/migrations/2026_11_02_device_pairing_v15.sql` migration and migration order before attempting a repair. Avoid re-running a migration that already completed.
5. If email login fails, verify the email stored in the same account. Email is optional; accounts without it must use User No. The password is unchanged by the username-removal migration.
6. If attempts are rate-limited, wait until the 15-minute login-attempt window expires; do not repeatedly try guessed passwords.

## Account diagnostics (read-only SQL)

```sql
SELECT id,user_no,name,email,role,must_change_password
FROM users
WHERE role='admin'
ORDER BY id;
```

Do not share passwords, password hashes or production database dumps. Don't try to sign in with Full Name; names can be shared by multiple users and are not authentication identifiers.

## Security

The recovery PHP script is **CLI-only**, selects an admin by exact User No, validates a nonempty new password and uses `password_hash()`. Run it only on a host where you control the application's database credentials. Do not copy the script into a publicly served directory or expose it as a web route.

### Version policy

The app and its native build versions remain **v1.5**. Schema revision numbers 16 and 17 are database schema migrations, not app version bumps.

## Environment troubleshooting

If PHP reports `MONITOR_DB_PASSWORD is missing`, it means the SSH shell did
not inherit Apache's `SetEnv` variables. Use the root-owned system helper above. It reads
`/etc/monitoring/apache-db.conf` with root privileges and drops privileges
to your regular user before executing the command. See
[ENV_MANAGEMENT.md](ENV_MANAGEMENT.md) for checks that do not reveal secrets.
