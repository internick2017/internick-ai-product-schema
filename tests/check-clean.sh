#!/usr/bin/env bash
# Run Plugin Check against a REAL folder named exactly 'internick-ai-product-schema' (matching the
# text domain), then restore the dev symlink. Avoids textdomain-vs-slug false
# errors caused by checking a differently-named dist folder.
set -uo pipefail

PLUGINS=/var/www/html/wp/wp-content/plugins
SRC=/var/www/html

# 1. Swap the dev symlink for a real 'internick-ai-product-schema' folder with only shipped files.
rm -f "$PLUGINS/internick-ai-product-schema"
rm -rf "$PLUGINS/internick-ai-product-schema"
mkdir -p "$PLUGINS/internick-ai-product-schema"
cp "$SRC/internick-ai-product-schema.php" "$SRC/readme.txt" "$SRC/README.md" "$SRC/uninstall.php" "$PLUGINS/internick-ai-product-schema/"
cp -r "$SRC/src" "$SRC/languages" "$SRC/assets" "$PLUGINS/internick-ai-product-schema/"

# 2. Run Plugin Check.
echo "=== PLUGIN CHECK (real 'internick-ai-product-schema' folder) ==="
wp --path=/var/www/html/wp plugin check internick-ai-product-schema --format=csv
echo "=== END PLUGIN CHECK ==="

# 3. Restore the dev symlink so the live env keeps tracking the repo root.
rm -rf "$PLUGINS/internick-ai-product-schema"
ln -sf /var/www/html "$PLUGINS/internick-ai-product-schema"
echo "Dev symlink restored."
