<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bucket;
use Illuminate\Support\Str;

class BucketController extends Controller
{
    // Buat Bucket Baru
    public function createBucket(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:buckets,name',
            'visibility' => 'in:public,private',
            'versioning' => 'boolean',
            'object_lock' => 'boolean'
        ]);

        $bucket = Bucket::create([
            'name' => $request->name,
            'storage_path' => "storage/{$request->name}",
            'access_key' => Str::random(20),
            'secret_key' => Str::random(40)
        ]);

        return response()->json([
            'message' => 'Bucket created successfully',
            'access_key' => $bucket->access_key,
            'secret_key' => $bucket->secret_key
        ]);
    }

    // List Semua Bucket
    public function listBuckets()
    {
        return response()->json(Bucket::all());
    }
}
