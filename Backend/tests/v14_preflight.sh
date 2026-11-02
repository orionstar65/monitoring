#!/usr/bin/env bash
# ACC Monitor v1.4 server preflight. Read-only: never changes DB or runs CI.
set -euo pipefail
cd "$(dirname "$0")/../.."
command -v php >/dev/null || { echo "php CLI required" >&2; exit 1; }
echo "[1/3] PHP syntax checks..."
count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null || { echo "Failed: $file" >&2; exit 1; }
  count=$((count+1))
done < <(find Backend -name '*.php' -type f -print0)
echo "OK: $count PHP files"
echo "[2/3] Migration files..."
for file in Backend/migrations/2026_11_02_identity.sql \
            Backend/migrations/2026_11_02_client_v14.sql \
            Backend/migrations/2026_11_02_accounts_v14.sql \
            Backend/migrations/2026_11_02_legacy_owners_v14.sql \
            Backend/migrations/2026_11_02_drop_legacy_username_v14.sql; do
  test -s "$file" || { echo "Missing migration: $file" >&2; exit 1; }
done
echo "OK: all v1.4 migrations present"
echo "[3/3] Frontend and API files..."
for file in Backend/client_auth.php \
            Backend/html/app/controllers/Merge.php \
            Backend/html/app/controllers/Monitors.php \
            Backend/html/app/controllers/Users.php \
            Backend/html/app/views/auth/change_password.php \
            Backend/html/app/views/users/index.php \
            Backend/html/app/views/monitors/index.php; do
  test -s "$file" || { echo "Missing file: $file" >&2; exit 1; }
done
php Backend/tests/identity_guards_test.php
echo "PASS: v1.4 source preflight. No database changes were made."
