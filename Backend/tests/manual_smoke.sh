#!/usr/bin/env bash
# Run manually after deployment. Does not create users, sessions or monitoring data.
# Example: bash Backend/tests/manual_smoke.sh https://monitor.example.com
set -euo pipefail
BASE="${1:-}"
if [[ ! "$BASE" =~ ^https://[^/]+$ ]]; then
  echo "Usage: bash Backend/tests/manual_smoke.sh https://your-monitor-host" >&2
  exit 2
fi
status() {
  curl --silent --show-error --location --output /dev/null --write-out '%{http_code}' \
    --max-time 15 "$@"
}
assert_status() {
  local name="$1" expected="$2" got="$3"
  if [[ "$got" == "$expected" ]]; then
    printf 'PASS %-30s %s\n' "$name" "$got"
  else
    printf 'FAIL %-30s wanted %s, got %s\n' "$name" "$expected" "$got" >&2
    exit 1
  fi
}
assert_status "client login GET disallowed" 405 "$(status "$BASE/client_auth.php")"
assert_status "unauthenticated telemetry" 401 "$(status -X POST -H 'Content-Type: application/json' -d '{"Event":"Tic"}' "$BASE/eventhandler.php")"
assert_status "unauthenticated screenshots" 401 "$(status -X POST "$BASE/webapi.php?type=upload")"
assert_status "raw screenshot directory denied" 403 "$(status "$BASE/html/data/")"
assert_status "raw exports directory denied" 403 "$(status "$BASE/html/exports/")"
assert_status "signup screen" 200 "$(status "$BASE/html/auth/signup")"
echo 'Manual anonymous API smoke checks passed. Complete authenticated role/monitor isolation tests separately.'
