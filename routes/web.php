<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/games/{slug}/{version}/{path}', [GameController::class, 'serveGame'])
    ->where('path', '.*')
    ->name('serve.game');
