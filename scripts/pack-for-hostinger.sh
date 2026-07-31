#!/bin/bash
# Pack a lean Laravel upload for Hostinger (excludes junk that burns disk/inodes).
# Usage (from project root): bash scripts/pack-for-hostinger.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/evomi-backend-hostinger.tar.gz"

cd "$ROOT"

if [ ! -d vendor ]; then
  echo "ERROR: vendor/ missing. Run: composer install --no-dev --optimize-autoloader"
  exit 1
fi

echo "==> Optimizing Laravel caches for production pack"
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

# Prefer --no-dev vendor before packing if this tree has require-dev installed
echo "==> Tip: for smallest upload run first:"
echo "    composer install --no-dev --optimize-autoloader --no-interaction"

echo "==> Creating $OUT"
tar -czf "$OUT" \
  --exclude='./.git' \
  --exclude='./.ai' \
  --exclude='./.kiro' \
  --exclude='./.cursor' \
  --exclude='./node_modules' \
  --exclude='./tests' \
  --exclude='./storage/logs/*' \
  --exclude='./storage/framework/cache/data/*' \
  --exclude='./storage/framework/sessions/*' \
  --exclude='./storage/framework/views/*' \
  --exclude='./storage/pail' \
  --exclude='./bootstrap/cache/*.php' \
  --exclude='./*.zip' \
  --exclude='./*.tar.gz' \
  --exclude='./evomi-backend.zip' \
  --exclude='./.env' \
  --exclude='./public/hot' \
  --exclude='./public/storage' \
  .

SIZE=$(du -h "$OUT" | cut -f1)
echo "DONE: $OUT ($SIZE)"
echo
echo "Upload & extract to Hostinger, then:"
echo "  1) Copy .env (production values from .env.example comments)"
echo "  2) php artisan key:generate   # if empty"
echo "  3) php artisan migrate --force"
echo "  4) php artisan config:cache && php artisan route:cache"
echo "  5) ln -sfn storage/app/public public/storage"
