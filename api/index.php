<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 1. Muat Autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// 2. Inisialisasi Aplikasi Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';

// 3. Tangani Request Masuk dan Kirimkan Response
$status = $app->handleRequest(Request::capture());

exit($status);