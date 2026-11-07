#!/usr/bin/env python3
"""Copy one platform build into an HTTP-served update directory.

Example: python3 scripts/publish_update.py --platform ubuntu-amd64 \
  --version 1.5.1 --package Ubuntu/MonitorClient-1.5.1-ubuntu-amd64.deb

Serve: python3 -m http.server 8765 --bind 0.0.0.0 --directory release-updates
"""
from __future__ import annotations
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import sys
import tempfile
from datetime import datetime, timezone

EXTENSIONS = {
    "windows-x64": ".zip",
    "macos-arm64": ".pkg",
    "macos-x64": ".pkg",
    "ubuntu-amd64": ".deb",
}
VERSION_RE = re.compile(r"^[0-9]+\.[0-9]+\.[0-9]+$")
MAX_BYTES = 1024 * 1024 * 1024


def publish(platform: str, version: str, package: Path, output: Path) -> dict:
    if platform not in EXTENSIONS:
        raise ValueError(f"Unsupported platform {platform}")
    if not VERSION_RE.fullmatch(version):
        raise ValueError("Version must be numeric MAJOR.MINOR.PATCH, e.g. 1.5.1")
    expected = f"MonitorClient-{version}-{platform}{EXTENSIONS[platform]}"
    if package.name != expected:
        raise ValueError(f"Expected package name: {expected}; got {package.name}")
    if not package.is_file() or not 0 < package.stat().st_size <= MAX_BYTES:
        raise ValueError("Package is missing, empty or larger than 1 GiB")
    output.mkdir(parents=True, exist_ok=True)
    digest = hashlib.sha256()
    size = 0
    with package.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
            size += len(block)
    manifest_file = output / "manifest.json"
    manifest = json.loads(manifest_file.read_text(encoding="utf-8")) if manifest_file.is_file() else {}
    if not isinstance(manifest, dict) or manifest.get("schema", 1) != 1:
        raise ValueError("Invalid existing manifest")
    platforms = manifest.setdefault("platforms", {})
    if not isinstance(platforms, dict):
        raise ValueError("Invalid existing manifest platforms")
    current = platforms.get(platform)
    if isinstance(current, dict) and current.get("version"):
        def parts(value: str) -> tuple[int, ...]:
            if not VERSION_RE.fullmatch(value):
                raise ValueError(f"Invalid existing version {value}")
            return tuple(map(int, value.split(".")))
        if parts(version) < parts(str(current["version"])):
            raise ValueError("Refusing to publish a downgrade of the current latest version")
        # Releasing different bytes under the same version silently strands
        # existing clients: their update check sees no newer version.
        if parts(version) == parts(str(current["version"])) and current.get("sha256") != digest.hexdigest():
            raise ValueError(
                "Refusing to overwrite a release with different bytes at the same version; "
                "increment the patch version for every new build"
            )
    destination = output / expected
    if package.resolve() != destination.resolve():
        # Atomic release publication; manifest is changed only after package is complete.
        with tempfile.NamedTemporaryFile(prefix=".package-", dir=output, delete=False) as temp:
            temporary = Path(temp.name)
        try:
            shutil.copyfile(package, temporary)
            os.replace(temporary, destination)
        finally:
            temporary.unlink(missing_ok=True)
    platforms[platform] = {
        "version": version,
        "file": expected,
        "sha256": digest.hexdigest(),
        "size": size,
    }
    manifest["schema"] = 1
    manifest["generated_at"] = datetime.now(timezone.utc).isoformat()
    with tempfile.NamedTemporaryFile(
        mode="w", encoding="utf-8", dir=output, prefix=".manifest-", delete=False
    ) as temp:
        json.dump(manifest, temp, indent=2, sort_keys=True)
        temp.write("\n")
        temporary_manifest = Path(temp.name)
    try:
        os.replace(temporary_manifest, manifest_file)
    finally:
        temporary_manifest.unlink(missing_ok=True)
    return platforms[platform]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--platform", choices=sorted(EXTENSIONS), required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--package", type=Path, required=True)
    parser.add_argument("--output", type=Path, default=Path("release-updates"))
    args = parser.parse_args()
    try:
        entry = publish(args.platform, args.version, args.package, args.output)
    except (ValueError, OSError, json.JSONDecodeError) as exc:
        parser.error(str(exc))
    print(json.dumps(entry, indent=2))
    print(f"Serve {args.output.resolve()} with: python3 -m http.server 8765 --directory {args.output}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
