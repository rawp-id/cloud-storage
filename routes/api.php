<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\StorageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Manajemen Bucket
Route::post('/bucket', [BucketController::class, 'createBucket']);
Route::get('/buckets', [BucketController::class, 'listBuckets']);

// Manajemen File (dengan Middleware Keamanan)
Route::middleware('apiauth')->group(function () {
    Route::post('/upload', [StorageController::class, 'uploadFile']);
    Route::get('/download/{filename}', [StorageController::class, 'downloadFile']);
    Route::delete('/delete/{filename}', [StorageController::class, 'hardDeleteFile']);
    Route::get('/signed-url/{filename}', [StorageController::class, 'generateSignedUrl']);
    Route::patch('/visibility/{filename}', [StorageController::class, 'setVisibility']);
    Route::post('/storage/soft-delete/{filename}', [StorageController::class, 'softDeleteFile']);
});
