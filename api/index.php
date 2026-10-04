<?php

// 1. TANGANI CORS SECARA MANUAL DI LEVEL PHP RAW (Mencegah Preflight Error)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 2. LOAD COMPOSER AUTOLOADER
require __DIR__.'/../vendor/autoload.php';

// 3. BOOTSTRAP LARAVEL
$app = require_once __DIR__.'/../bootstrap/app.php';

// 4. PAKSA FOLDER STORAGE KE /TMP (Mencegah Error 500 Read-Only Vercel)
$app->useStoragePath('/tmp');

// 5. TANGANI REQUEST (Standar Laravel Terbaru pengganti Kernel)
$app->handleRequest(Request::capture());