#!/usr/bin/env python3
"""Test the self-hosted update manifest publisher without native toolchains."""
import hashlib
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

FILE = Path(__file__).resolve().parent / "publish_update.py"
spec = importlib.util.spec_from_file_location("publish_update", FILE)
module = importlib.util.module_from_spec(spec)
sys.modules["publish_update"] = module
spec.loader.exec_module(module)


class ReleasePublisherTests(unittest.TestCase):
    def test_all_platforms_and_hashes(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            out = root / "hosted"
            for arch, ext in module.EXTENSIONS.items():
                filename = f"MonitorClient-1.5.1-{arch}{ext}"
                package = root / filename
                content = f"fake deterministic test package for {arch}".encode()
                package.write_bytes(content)
                row = module.publish(arch, "1.5.1", package, out)
                self.assertEqual(row["file"], filename)
                self.assertEqual(row["size"], len(content))
                self.assertEqual(row["sha256"], hashlib.sha256(content).hexdigest())
                self.assertEqual((out / filename).read_bytes(), content)
            manifest = json.loads((out / "manifest.json").read_text())
            self.assertEqual(manifest["schema"], 1)
            self.assertEqual(set(manifest["platforms"]), set(module.EXTENSIONS))

    def test_version_order_and_names(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            out = root / "served"
            good = root / "MonitorClient-1.5.2-ubuntu-amd64.deb"
            good.write_bytes(b"debian package placeholder")
            module.publish("ubuntu-amd64", "1.5.2", good, out)
            previous = root / "MonitorClient-1.5.1-ubuntu-amd64.deb"
            previous.write_bytes(b"older package placeholder")
            with self.assertRaisesRegex(ValueError, "downgrade"):
                module.publish("ubuntu-amd64", "1.5.1", previous, out)
            with self.assertRaisesRegex(ValueError, "Expected package name"):
                module.publish("ubuntu-amd64", "1.5.3", previous, out)
            with self.assertRaisesRegex(ValueError, "MAJOR.MINOR.PATCH"):
                module.publish("ubuntu-amd64", "1.5", previous, out)
            self.assertEqual(json.loads((out / "manifest.json").read_text())
                             ["platforms"]["ubuntu-amd64"]["version"], "1.5.2")

    def test_same_version_is_idempotent_but_cannot_change_release_bytes(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            out = root / "served"
            package = root / "MonitorClient-1.5.1-ubuntu-amd64.deb"
            package.write_bytes(b"version one contents")
            first = module.publish("ubuntu-amd64", "1.5.1", package, out)
            # An identical retry is safe and should keep the same checksum.
            second = module.publish("ubuntu-amd64", "1.5.1", package, out)
            self.assertEqual(first["sha256"], second["sha256"])
            package.write_bytes(b"new contents but accidentally old version")
            with self.assertRaisesRegex(ValueError, "increment the patch version"):
                module.publish("ubuntu-amd64", "1.5.1", package, out)
            self.assertEqual((out / package.name).read_bytes(), b"version one contents")
            self.assertEqual(
                json.loads((out / "manifest.json").read_text())
                ["platforms"]["ubuntu-amd64"]["sha256"], first["sha256"]
            )

    def test_refuse_missing_empty_and_unsupported(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            package = root / "MonitorClient-1.5.1-windows-x64.zip"
            with self.assertRaises(ValueError):
                module.publish("windows-x64", "1.5.1", package, root / "out")
            package.write_bytes(b"")
            with self.assertRaises(ValueError):
                module.publish("windows-x64", "1.5.1", package, root / "out")
            package.write_bytes(b"x")
            with self.assertRaises(ValueError):
                module.publish("windows-arm64", "1.5.1", package, root / "out")


if __name__ == "__main__":
    unittest.main(verbosity=2)
