<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$request = Request::capture();

// Support subdirectory deploy (e.g. https://evomi.shop/backend)
$prefixes = ['/backend/public', '/backend'];
$appUrlPath = parse_url((string) env('APP_URL', ''), PHP_URL_PATH);
if (is_string($appUrlPath) && $appUrlPath !== '' && $appUrlPath !== '/') {
    $prefix = rtrim($appUrlPath, '/');
    array_unshift($prefixes, $prefix.'/public', $prefix);
}
$prefixes = array_values(array_unique($prefixes));

foreach ($prefixes as $prefix) {
    $uri = (string) $request->server->get('REQUEST_URI', '/');
    if (str_starts_with($uri, $prefix.'/') || $uri === $prefix) {
        $trimmed = substr($uri, strlen($prefix));
        if ($trimmed === '' || ! str_starts_with($trimmed, '/')) {
            $trimmed = '/'.$trimmed;
        }
        $request->server->set('REQUEST_URI', $trimmed);
        break;
    }
}

$app->handleRequest($request);
