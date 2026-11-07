#!/usr/bin/env bash
# Register one validated, versioned OS release in the backend update repository.
# Run on the BACKEND server after uploading the OS installer there:
#   bash Backend/publish_upgrade.sh ubuntu-amd64 1.5.3 /path/to/MonitorClient-1.5.3-ubuntu-amd64.deb
# A single shared manifest is maintained; never overwrite it using stale
# manifests generated on independent Mac/Windows/Ubuntu build hosts.
set -euo pipefail
if [[ $# -ne 3 ]]; then
  echo "Usage: $0 <ubuntu-amd64|windows-x64|macos-arm64|macos-x64> <X.Y.Z> <package path>" >&2
  exit 2
fi
platform="$1"
version="$2"
package="$3"
case "$platform" in
  ubuntu-amd64) ext=deb ;;
  windows-x64) ext=zip ;;
  macos-arm64|macos-x64) ext=pkg ;;
  *) echo "Unsupported platform: $platform" >&2; exit 2 ;;
esac
if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Version must be MAJOR.MINOR.PATCH" >&2; exit 2
fi
expected="MonitorClient-${version}-${platform}.${ext}"
if [[ "${package##*/}" != "$expected" || ! -f "$package" ]]; then
  echo "Expected an existing installer named: $expected" >&2; exit 2
fi
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output="$root/release-updates"
mkdir -p "$output"
python3 "$root/scripts/publish_update.py" --platform "$platform" \
  --version "$version" --package "$package" --output "$output"
printf 'Backend update manifest: %s/manifest.json\n' "$output"
printf 'Check over Apache: http(s)://YOUR_SERVER:YOUR_PORT/upgrades/manifest.json\n'
