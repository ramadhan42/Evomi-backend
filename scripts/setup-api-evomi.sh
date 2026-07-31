#!/bin/bash
# Jalankan SETELAH subdomain api.evomi.shop dibuat di hPanel sebagai website PHP terpisah.
# Usage: bash ~/setup-api-evomi.sh
set -euo pipefail

SRC="/home/u160994497/domains/evomi.shop/laravel"
API_ROOT="/home/u160994497/domains/api.evomi.shop"
API_PUBLIC="$API_ROOT/public_html"

if [ ! -d "$SRC/vendor" ]; then
  echo "ERROR: sumber Laravel tidak ketemu di $SRC"
  exit 1
fi

if [ ! -d "$API_ROOT" ]; then
  echo "ERROR: folder $API_ROOT belum ada."
  echo "Buat dulu website/subdomain api.evomi.shop di hPanel (tipe PHP), lalu jalankan script ini lagi."
  exit 1
fi

echo "==> Copy Laravel ke api.evomi.shop (di luar public_html Node)"
mkdir -p "$API_ROOT/laravel"
rsync -a --delete \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='evomi-backend.zip' \
  "$SRC/" "$API_ROOT/laravel/"

echo "==> Arahkan public_html subdomain ke Laravel public/"
# Backup default public_html sekali
if [ -d "$API_PUBLIC" ] && [ ! -L "$API_PUBLIC" ]; then
  if [ ! -e "$API_ROOT/public_html.bak" ]; then
    mv "$API_PUBLIC" "$API_ROOT/public_html.bak"
  else
    rm -rf "$API_PUBLIC"
  fi
fi
ln -sfn "$API_ROOT/laravel/public" "$API_PUBLIC"

echo "==> Storage link"
rm -rf "$API_ROOT/laravel/public/storage"
ln -sfn "$API_ROOT/laravel/storage/app/public" "$API_ROOT/laravel/public/storage"
chmod -R ug+rwx "$API_ROOT/laravel/storage" "$API_ROOT/laravel/bootstrap/cache" || true

echo "==> Update .env untuk api.evomi.shop"
php -r "
\$p='$API_ROOT/laravel/.env';
\$lines=file(\$p, FILE_IGNORE_NEW_LINES);
\$force=[
  'APP_ENV'=>'production',
  'APP_DEBUG'=>'false',
  'APP_URL'=>'https://api.evomi.shop',
  'FRONTEND_URL'=>'https://evomi.shop',
  'LOG_LEVEL'=>'error',
  'LOG_CHANNEL'=>'stack',
  'SESSION_DRIVER'=>'file',
  'CACHE_STORE'=>'file',
  'QUEUE_CONNECTION'=>'sync',
  'BCRYPT_ROUNDS'=>'10',
];
\$seen=[]; \$out=[];
foreach (\$lines as \$line) {
  if (\$line==='' || str_starts_with(ltrim(\$line),'#') || !str_contains(\$line,'=')) { \$out[]=\$line; continue; }
  \$k=explode('=',\$line,2)[0];
  if (isset(\$force[\$k])) { \$out[]=\$k.'='.\$force[\$k]; \$seen[\$k]=true; }
  else \$out[]=\$line;
}
foreach (\$force as \$k=>\$v) if (!isset(\$seen[\$k])) \$out[]=\$k.'='.\$v;
file_put_contents(\$p, implode(\"\\n\", \$out).\"\\n\");
echo \"ENV_OK\\n\";
"

echo "==> Simplify public/index.php (tanpa strip /backend)"
cat > "$API_ROOT/laravel/public/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP

echo "==> public/.htaccess standar Laravel"
cat > "$API_ROOT/laravel/public/.htaccess" <<'HTA'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTA

cd "$API_ROOT/laravel"
php artisan config:clear || true
php artisan route:clear || true

echo
echo "SELESAI."
echo "Tes:"
echo "  https://api.evomi.shop/up"
echo "  https://api.evomi.shop/api/products"
echo
echo "Lalu update frontend NEXT_PUBLIC_URL=https://api.evomi.shop dan redeploy Node."
