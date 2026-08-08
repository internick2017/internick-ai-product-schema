#!/usr/bin/env bash
# Build the WordPress.org distribution ZIP with the root folder named exactly
# 'internick-ai-product-schema' (must match the text domain) and only the shipped files.
set -euo pipefail

SRC=/var/www/html
STAGE=/tmp/internick-aips-pkg
DEST=$STAGE/internick-ai-product-schema

rm -rf "$STAGE"
mkdir -p "$DEST"

cp "$SRC/internick-ai-product-schema.php" "$SRC/readme.txt" "$SRC/uninstall.php" "$DEST/"
cp -r "$SRC/src" "$SRC/languages" "$SRC/assets" "$DEST/"

mkdir -p "$SRC/dist"
rm -f "$SRC/dist/internick-ai-product-schema.zip"
( cd "$STAGE" && zip -rq "$SRC/dist/internick-ai-product-schema.zip" internick-ai-product-schema )

echo "Built dist/internick-ai-product-schema.zip"
echo "--- contents ---"
( cd "$SRC/dist" && unzip -l internick-ai-product-schema.zip )
