<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Hostinger subdirectory entrypoint for https://evomi.shop/backend
 * (same role as public/index.php, but paths are relative to project root)
 */
define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
