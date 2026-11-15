#!/usr/bin/env bash
# Byte-reproducible WordPress plugin ZIP for PeeStream Multi-Hoster.
# Fixed timestamps + allowlisted files + explicit compression, so two runs with the
# same sources always produce the same SHA-256. Writes dist/ and SHA256SUMS.
set -euo pipefail
cd "$(dirname "$0")/.."

VER=$(sed -n "s/^define('PSMH_VERSION', '\([^']*\)');$/\1/p" peestream-multihoster.php)
[ -n "$VER" ] || { echo "ERROR: cannot read PSMH_VERSION" >&2; exit 1; }

NAME="peestream-multihoster.zip"
PREFIX="peestream-multihoster/"
FILES=(
  peestream-multihoster.php
  README.md
  CHANGELOG.md
  LICENSE
  admin/admin.js
  admin/class-credentials-admin.php
  admin/class-multihoster-admin.php
  includes/adapters/class-peestream-adapter.php
  includes/class-adapter-registry.php
  includes/class-credentials.php
  includes/class-healing-queue.php
  includes/class-healing-worker.php
  includes/class-multihoster-health.php
  includes/class-multihoster-meta.php
  includes/class-multihoster-player.php
  includes/interface-hoster-adapter.php
)

for f in "${FILES[@]}"; do
  [ -f "$f" ] || { echo "ERROR: missing package candidate $f" >&2; exit 1; }
done

mkdir -p dist
python3 - "$NAME" "$PREFIX" "${FILES[@]}" <<'PY'
import os, sys, zipfile
name, prefix = sys.argv[1], sys.argv[2]
files = sys.argv[3:]
stamp = (2026, 11, 15, 0, 0, 0)   # fixed, keeps the archive reproducible
out = os.path.join("dist", name)
with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for rel in files:
        with open(rel, "rb") as fh:
            data = fh.read()
        info = zipfile.ZipInfo(prefix + rel, date_time=stamp)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        info.external_attr = (0o644 & 0xFFFF) << 16
        z.writestr(info, data, compresslevel=9)
print("built %s (%d bytes, %d files)" % (out, os.path.getsize(out), len(files)))
PY

( cd dist && sha256sum "$NAME" ) > SHA256SUMS
echo "version ${VER}"
cat SHA256SUMS
