<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageController;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/files', [StorageController::class, 'getAll']);
Route::get('/{bucket}/{filename}', [StorageController::class, 'showFile']);