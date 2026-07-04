#!/usr/bin/env bash
# Run Plugin Check against a REAL folder named exactly 'shopgraph' (matching the
# text domain), then restore the dev symlink. Avoids textdomain-vs-slug false
# errors caused by checking a differently-named dist folder.
set -uo pipefail

PLUGINS=/var/www/html/wp/wp-content/plugins
SRC=/var/www/html

# 1. Swap the dev symlink for a real 'shopgraph' folder with only shipped files.
rm -f "$PLUGINS/shopgraph"
rm -rf "$PLUGINS/shopgraph"
mkdir -p "$PLUGINS/shopgraph"
cp "$SRC/shopgraph.php" "$SRC/readme.txt" "$SRC/README.md" "$PLUGINS/shopgraph/"
cp -r "$SRC/src" "$SRC/languages" "$SRC/assets" "$PLUGINS/shopgraph/"

# 2. Run Plugin Check.
echo "=== PLUGIN CHECK (real 'shopgraph' folder) ==="
wp --path=/var/www/html/wp plugin check shopgraph --format=csv
echo "=== END PLUGIN CHECK ==="

# 3. Restore the dev symlink so the live env keeps tracking the repo root.
rm -rf "$PLUGINS/shopgraph"
ln -sf /var/www/html "$PLUGINS/shopgraph"
echo "Dev symlink restored."
