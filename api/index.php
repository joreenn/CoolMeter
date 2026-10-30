<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Vercel's project files are read-only. Only disposable framework files belong in /tmp.
$scratch = sys_get_temp_dir().'/coolmeter';
foreach (['', '/framework/views', '/framework/cache/data', '/framework/sessions', '/logs'] as $directory) {
    $path = $scratch.$directory;
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        http_response_code(503);
        exit('Temporary storage is unavailable.');
    }
}

foreach ([
    'APP_CONFIG_CACHE' => $scratch.'/config.php', 'APP_EVENTS_CACHE' => $scratch.'/events.php',
    'APP_PACKAGES_CACHE' => $scratch.'/packages.php', 'APP_ROUTES_CACHE' => $scratch.'/routes.php',
    'APP_SERVICES_CACHE' => $scratch.'/services.php', 'VIEW_COMPILED_PATH' => $scratch.'/framework/views',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

// Never deploy a temporary SQLite database as if it were permanent online storage.
$databaseUrl = getenv('DB_URL') ?: getenv('POSTGRES_URL') ?: getenv('DATABASE_URL');
if (! $databaseUrl || ! getenv('APP_KEY')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('CoolMeter is awaiting its hosted database and application key.');
}

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($scratch);
// The adapter lives under /api, but Laravel's public URLs start at /. Otherwise
// Symfony can mistake /api for a base directory and strip it from API routes.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$app->handleRequest(Request::capture());
