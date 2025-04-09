<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\StorageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('register', [AuthController::class, 'register']);

// Manajemen Bucket


// Manajemen File (dengan Middleware Keamanan)
Route::middleware('apiauth')->group(function () {
    Route::post('/bucket', [BucketController::class, 'createBucket']);
    Route::get('/buckets', [BucketController::class, 'listBuckets']);
    Route::get('/buckets/{bucket}', [BucketController::class, 'getBucket']);
    Route::put('/buckets/{bucket}', [BucketController::class, 'updateBucket']);
    Route::delete('/buckets/{bucket}', [BucketController::class, 'deleteBucket']);
    Route::post('/upload', [StorageController::class, 'uploadFile']);
    Route::get('/download/{filename}', [StorageController::class, 'downloadFile']);
    Route::delete('/delete/{filename}', [StorageController::class, 'hardDeleteFile']);
    Route::get('/signed-url', [StorageController::class, 'generateSignedUrl']);
    Route::get('/signed-url/{filename}', [StorageController::class, 'generateSignedUrl']);
    Route::patch('/visibility', [StorageController::class, 'setVisibility']);
    Route::patch('/visibility/{filename}', [StorageController::class, 'setVisibility']);
    Route::post('/storage/soft-delete/{filename}', [StorageController::class, 'softDeleteFile']);
});
