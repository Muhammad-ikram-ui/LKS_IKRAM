<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'bootstrap/app.php';

$app = app();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/v1/games', 'GET');
$response = $kernel->handle($request);

echo $response->getContent();

$kernel->terminate($request, $response);
