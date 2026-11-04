#!/usr/bin/env python3
"""Run a local backend CLI command using the existing Apache database settings.

Usage:
    sudo python3 Backend/with_database_env.py php Backend/recover_web_login.php --list-admins

Read /etc/monitoring/apache-db.conf (Apache SetEnv syntax), import only the
MONITOR_DB_* environment variables, and drop privileges back to the invoking
sudo user. No database password is printed or saved in the Git checkout.
"""
from __future__ import annotations

import os
import getpass
import pathlib
import pwd
import shlex
import sys

CONFIG = pathlib.Path('/etc/monitoring/apache-db.conf')
ALLOWED = frozenset({'MONITOR_DB_HOST', 'MONITOR_DB_USER', 'MONITOR_DB_NAME', 'MONITOR_DB_PASSWORD'})


def fail(message: str) -> None:
    print(f'ERROR: {message}', file=sys.stderr)
    sys.exit(1)


def main() -> None:
    command = sys.argv[1:]
    prompt_recovery_password = bool(command and command[0] == '--prompt-recovery-password')
    if prompt_recovery_password:
        command = command[1:]
    if not command:
        fail('Usage: sudo python3 Backend/with_database_env.py [--prompt-recovery-password] php Backend/recover_web_login.php --list-admins')
    username = os.environ.get('SUDO_USER')
    if os.geteuid() != 0 or not username or username == 'root':
        fail('Run using sudo from your normal account (for example, user server).')
    try:
        metadata = CONFIG.stat()
        if not CONFIG.is_file() or metadata.st_uid != 0 or (metadata.st_mode & 0o077):
            fail('Apache database config must be a root-owned file with permissions 600.')
        environment = os.environ.copy()
        for raw in CONFIG.read_text(encoding='utf-8').splitlines():
            parts = shlex.split(raw, comments=True, posix=True)
            if not parts or parts[0] != 'SetEnv':
                continue
            if len(parts) == 3 and parts[1] in ALLOWED:
                environment[parts[1]] = parts[2]
        if not environment.get('MONITOR_DB_PASSWORD'):
            fail('Apache database config does not contain MONITOR_DB_PASSWORD. Verify setup_database_access.sh has been run.')
        if prompt_recovery_password:
            if not (len(command) >= 3 and command[0] == 'php'
                    and command[1].endswith('/recover_web_login.php')
                    and command[2].startswith('--reset-user-no=')):
                fail('--prompt-recovery-password is only for the administrator recovery command.')
            password = getpass.getpass('New admin password (12+ characters): ')
            confirmation = getpass.getpass('Confirm new password: ')
            if password != confirmation:
                fail('Passwords did not match.')
            if not 12 <= len(password) <= 1024:
                fail('Password must contain 12-1024 characters.')
            environment['MONITOR_RECOVERY_PASSWORD'] = password
            del password
            del confirmation
        target = pwd.getpwnam(username)
        os.initgroups(username, target.pw_gid)
        os.setgid(target.pw_gid)
        os.setuid(target.pw_uid)
        os.execvpe(command[0], command, environment)
    except FileNotFoundError:
        fail('Missing /etc/monitoring/apache-db.conf. Check Apache setup before proceeding.')
    except (OSError, ValueError) as error:
        fail(f'Unable to load the Apache environment: {error}')


if __name__ == '__main__':
    main()
