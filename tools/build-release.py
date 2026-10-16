#!/usr/bin/env python3
"""Build a WordPress release ZIP and update.json without development files."""
import argparse
import hashlib
import json
import re
import subprocess
import zipfile
from datetime import datetime, timezone
from pathlib import Path


def build(component, root, output, tag=None, notes_file=None):
    header_path = root / ("style.css" if component == "theme" else "timellow.php")
    header = header_path.read_text(encoding="utf-8")

    def field(name):
        match = re.search(r"^\s*(?:\*\s*)?" + re.escape(name) + r":\s*(.+)$", header, re.M)
        if not match:
            raise ValueError(f"Missing header: {name}")
        return match.group(1).strip()

    version = field("Version")
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:\.\d+)?", version):
        raise ValueError("Releases require a stable numeric version.")
    if tag and tag != f"v{version}":
        raise ValueError(f"Tag {tag} does not match header version v{version}.")
    if component == "plugin" and f"define('TIMELLOW_PLUGIN_VERSION', '{version}');" not in header:
        raise ValueError("Plugin header and runtime versions differ.")
    repo = f"jkjoy/wordpress-{component}-timellow"
    paths = subprocess.check_output(
        ["git", "ls-files", "-z", "--cached", "--others", "--exclude-standard"], cwd=root
    ).decode().split("\0")
    output.mkdir(parents=True, exist_ok=True)
    package_path = output / "timellow.zip"
    skipped = {"plugins", "tools", "dist", "tests", "__pycache__"}
    with zipfile.ZipFile(package_path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(set(paths)):
            path = root / name
            if not name or not path.is_file() or path.is_symlink():
                continue
            if any(part.startswith(".") or part in skipped for part in Path(name).parts):
                continue
            info = zipfile.ZipInfo("timellow/" + name, (2020, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes(), compresslevel=9)
    changelog = notes_file.read_text(encoding="utf-8") if notes_file else f"Timellow {version}\nhttps://github.com/{repo}/releases/tag/v{version}"
    manifest = {
        "type": component,
        "version": version,
        "requires": field("Requires at least"),
        "requires_php": field("Requires PHP"),
        "package": f"https://github.com/{repo}/releases/download/v{version}/timellow.zip",
        "sha256": hashlib.sha256(package_path.read_bytes()).hexdigest(),
        "last_updated": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S"),
        "changelog": changelog,
    }
    (output / "update.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Built {component} {version}: {package_path}")
    return manifest


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("component", choices=["theme", "plugin"])
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parent.parent)
    parser.add_argument("--output", type=Path, default=Path("dist"))
    parser.add_argument("--tag")
    parser.add_argument("--notes-file", type=Path)
    args = parser.parse_args()
    build(args.component, args.root.resolve(), args.output.resolve(), args.tag, args.notes_file)
