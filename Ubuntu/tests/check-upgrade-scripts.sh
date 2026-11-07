#!/usr/bin/env bash
# No package installation, process signals or GitHub Actions.
# Run from any directory: bash Ubuntu/tests/check-upgrade-scripts.sh
set -euo pipefail
project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
for script in \
    "$project_root/Ubuntu/upgrade-restart.sh" \
    "$project_root/Ubuntu/create_deb_package.sh" \
    "$project_root/Ubuntu/install.sh" \
    "$project_root/Backend/publish_upgrade.sh"; do
    bash -n "$script"
    printf 'PASS Bash syntax: %s\n' "${script#"$project_root/"}"
done
if command -v php >/dev/null 2>&1; then
    php "$project_root/Backend/tests/ubuntu_upgrade_contract.php"
    php "$project_root/Backend/tests/upgrades_and_settings_contract.php"
fi
if command -v python3 >/dev/null 2>&1; then
    python3 "$project_root/scripts/test_publish_update.py"
fi
