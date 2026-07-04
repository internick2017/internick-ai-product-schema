#!/usr/bin/env bash
# Build a clean, shippable copy of the plugin (no vendor/wp/tests/.ddev/docs)
# into wp/wp-content/plugins/shopgraph-dist so Plugin Check and packaging see
# exactly what WordPress.org would receive.
set -euo pipefail

SRC=/var/www/html
DEST=/var/www/html/wp/wp-content/plugins/shopgraph-dist

rm -rf "$DEST"
mkdir -p "$DEST"

cp "$SRC/shopgraph.php" "$DEST/"
cp "$SRC/readme.txt" "$DEST/"
cp "$SRC/README.md" "$DEST/"
cp -r "$SRC/src" "$DEST/"
cp -r "$SRC/languages" "$DEST/"

echo "Dist built at $DEST"
echo "--- files ---"
( cd "$DEST" && find . -type f | sort )
