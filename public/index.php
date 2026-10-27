<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    // heratio#1569: robots.txt stays up during maintenance (the nightly demo
    // reset), from the copy PublicSitemapController keeps per host. A 503 on
    // robots.txt makes Google stop crawling the whole site.
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) === '/robots.txt'
        && preg_match('/^[a-z0-9][a-z0-9.-]{0,252}$/', $host)
        && is_file($robots = __DIR__.'/../storage/framework/robots/'.$host.'.txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        readfile($robots);
        exit;
    }
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
