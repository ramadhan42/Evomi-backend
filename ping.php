<?php

/**
 * Temporary Hostinger probe.
 * Open: https://evomi.shop/backend/ping.php
 * Delete this file after debugging.
 */
header('Content-Type: text/plain; charset=utf-8');

echo "pong\n";
echo 'php='.PHP_VERSION."\n";
echo 'index_exists='.(file_exists(__DIR__.'/index.php') ? 'yes' : 'no')."\n";
echo 'htaccess_exists='.(file_exists(__DIR__.'/.htaccess') ? 'yes' : 'no')."\n";
echo 'vendor_exists='.(is_dir(__DIR__.'/vendor') ? 'yes' : 'no')."\n";
echo 'env_exists='.(file_exists(__DIR__.'/.env') ? 'yes' : 'no')."\n";
