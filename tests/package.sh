#!/usr/bin/env bash
# Build the WordPress.org distribution ZIP with the root folder named exactly
# 'shopgraph' (must match the text domain) and only the shipped files.
set -euo pipefail

SRC=/var/www/html
STAGE=/tmp/shopgraph-pkg
DEST=$STAGE/shopgraph

rm -rf "$STAGE"
mkdir -p "$DEST"

cp "$SRC/shopgraph.php" "$SRC/readme.txt" "$DEST/"
cp -r "$SRC/src" "$SRC/languages" "$DEST/"

mkdir -p "$SRC/dist"
rm -f "$SRC/dist/shopgraph.zip"
( cd "$STAGE" && zip -rq "$SRC/dist/shopgraph.zip" shopgraph )

echo "Built dist/shopgraph.zip"
echo "--- contents ---"
( cd "$SRC/dist" && unzip -l shopgraph.zip )
