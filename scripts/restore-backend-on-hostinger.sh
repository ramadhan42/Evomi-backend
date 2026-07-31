#!/bin/bash
# Restore Laravel /backend after Hostinger Node.js redeploy wipes public_html
set -e
SRC="/home/u160994497/domains/evomi.shop/laravel"
DST="/home/u160994497/domains/evomi.shop/public_html/backend"
HTACCESS="/home/u160994497/domains/evomi.shop/public_html/.htaccess"

if [ ! -d "$SRC" ]; then
  echo "Missing persistent Laravel at $SRC"
  exit 1
fi

rm -rf "$DST"
cp -a "$SRC" "$DST"
rm -rf "$DST/public/storage"
ln -s "$DST/storage/app/public" "$DST/public/storage"

# Ensure root htaccess keeps /backend out of Passenger
if ! grep -q 'RewriteRule \^backend' "$HTACCESS" 2>/dev/null; then
  tmp=$(mktemp)
  cat > "$tmp" <<'EOF'
# Allow Laravel PHP under /backend
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^backend(/|$) - [L]
</IfModule>

EOF
  cat "$HTACCESS" >> "$tmp"
  mv "$tmp" "$HTACCESS"
fi

cd "$DST" && php artisan config:clear
echo "Restored $DST"
