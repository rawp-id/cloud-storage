<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Bucket;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ObjectStorage;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    // public function getAll()
    // {
    //     $file = Storage::disk('local')->get('storage/mybucket/zakat.png');
    //     // return response()->file($file);
    //     return response($file)->header('Content-Type', 'image');
    // }

    public function uploadFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'visibility' => 'in:public,private',
                'locked_until' => 'integer'
            ]);

            $bucket = $request->bucket;
            $filename = $request->file('file')->getClientOriginalName();
            $path = "{$bucket->storage_path}/$filename";

            // Cek Object Lock
            if ($bucket->object_lock) {
                $lockedFile = ObjectStorage::where('bucket_id', $bucket->id)
                    ->where('key', $filename)
                    ->where('locked_until', '>', now())
                    ->exists();

                if ($lockedFile) {
                    return response()->json(['error' => 'File is locked and cannot be overwritten'], 403);
                }
            }

            // Cek Mode Versioning
            if ($bucket->versioning) {
                $versionId = Str::uuid();
                $path = "{$bucket->storage_path}/v{$versionId}_{$filename}";
            } else {
                // Hapus file lama jika versioning tidak aktif
                Storage::delete($path);
                ObjectStorage::where('bucket_id', $bucket->id)
                    ->where('key', $filename)
                    ->delete();
            }

            // Simpan file baru
            Storage::put($path, file_get_contents($request->file('file')));

            $lockedUntil = $request->locked_until ?? 30; // Default 30 hari lock

            $object = ObjectStorage::create([
                'bucket_id' => $bucket->id,
                'key' => $filename,
                'path' => $path,
                'version_id' => $bucket->versioning ? $versionId : null,
                'locked_until' => $bucket->object_lock ? now()->addDays($lockedUntil) : null, // Default 30 hari lock
                'visibility' => $request->visibility ?? 'private',
            ]);

            return response()->json(['message' => 'File uploaded', 'object' => $object]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Upload File ke Bucket
    // public function uploadFile(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file',
    //         'visibility' => 'in:public,private'
    //     ]);

    //     $bucket = $request->bucket;
    //     $file = $request->file('file');
    //     $filename = $file->getClientOriginalName();
    //     $path = "{$bucket->storage_path}/$filename";

    //     Storage::put($path, file_get_contents($file));

    //     $object = ObjectStorage::create([
    //         'bucket_id' => $bucket->id,
    //         'key' => $filename,
    //         'path' => $path,
    //         'visibility' => $request->visibility ?? 'private',
    //     ]);

    //     return response()->json(['message' => 'File uploaded', 'object' => $object]);
    // }

    // Generate Signed URL
    // public function generateSignedUrl(Request $request, $filename)
    // {
    //     $bucket = $request->bucket;
    //     $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->firstOrFail();

    //     if ($object->visibility === 'public') {
    //         return response()->json(['url' => Storage::url($object->path)]);
    //     }

    //     $expTime = $request->input('expTime', 10);
    //     $expiresAt = Carbon::now()->addMinutes($expTime); // URL Expire 10 Menit
    //     $signedUrl = Storage::temporaryUrl($object->path, $expiresAt);

    //     return response()->json(['signed_url' => $signedUrl]);
    // }

    public function generateSignedUrl(Request $request, $filename)
    {
        // dd($filename);
        $bucket = $request->bucket;
        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->first();

        if (!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if ($object->visibility === 'public') {
            // dd($object->path);
            return response()->json(['url' => url($object->path)]);
        }

        $expTime = max(min($request->input('expTime', 10), 60), 1); // Maks 60 menit, Min 1 menit
        $expiresAt = Carbon::now()->addMinutes($expTime)->timestamp;

        $secretKey = env('SIGNED_URL_SECRET', 'default_secret_key');
        $signature = hash_hmac('sha256', "{$filename}:{$expiresAt}", $secretKey);

        $signedUrl = url("/{$bucket->name}/{$filename}") .
            "?expires={$expiresAt}&signature={$signature}";

        return response()->json(['signed_url' => $signedUrl, 'expires_in' => $expTime . ' minutes']);
    }

    public function accessSignedUrl(Request $request, $bucketId, $filename)
    {
        $expiresAt = $request->query('expires');
        $signature = $request->query('signature');

        $object = ObjectStorage::where('bucket_id', $bucketId)->where('key', $filename)->first();

        if (!$expiresAt || !$signature) {
            return response()->json(['error' => 'Invalid signed URL'], 400);
        }

        if (Carbon::now()->timestamp > $expiresAt) {
            return response()->json(['error' => 'Signed URL expired'], 403);
        }

        $secretKey = env('SIGNED_URL_SECRET', 'default_secret_key');
        $expectedSignature = hash_hmac('sha256', "{$filename}:{$expiresAt}", $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        if (!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response(Storage::get($object->path))->header('Content-Type', 'file');
    }

    // Set File Visibility (Public/Private)
    public function setVisibility(Request $request, $filename)
    {
        $request->validate(['visibility' => 'required|in:public,private']);

        $bucket = $request->bucket;
        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->firstOrFail();

        $object->visibility = $request->visibility;
        $object->save();

        return response()->json(['message' => 'Visibility updated']);
    }

    // Download File
    public function downloadFile(Request $request, $filename)
    {
        $bucket = $request->bucket;
        $path = "{$bucket->storage_path}/$filename";

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::download($path);
    }

    public function showFile(Request $request, $bucket, $filename)
    {
        $bucket = Bucket::where('name', $bucket)->first();
        if (!$bucket) {
            return response()->json(['error' => 'Bucket not found'], 404);
        }
        // dd($bucket);
        $path = "{$bucket->storage_path}/$filename";
        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->firstOrFail();

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if ($object->visibility == 'private') {
            // return response()->json(['error' => 'File not found'], 404);
            return $this->accessSignedUrl($request, $bucket->id, $filename);
        }

        return response(Storage::get($path))->header('Content-Type', 'file');
    }

    // Hapus File
    public function hardDeleteFile(Request $request, $filename)
    {
        $bucket = $request->bucket;
        $path = "{$bucket->storage_path}/$filename";

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        Storage::delete($path);
        return response()->json(['message' => 'File deleted']);
    }

    public function softDeleteFile(Request $request)
    {
        $bucket = $request->bucket;
        $filename = $request->filename;

        $object = ObjectStorage::where('bucket_id', $bucket->id)
            ->where('key', $filename)
            ->orderByDesc('created_at') // Ambil versi terbaru
            ->first();

        if (!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // **Object Lock Aktif? Tidak bisa dihapus**
        if ($object->locked_until && now()->lessThan($object->locked_until)) {
            return response()->json(['error' => 'File is locked and cannot be deleted'], 403);
        }

        if ($bucket->versioning) {
            // **Versioning aktif: Gunakan delete marker**
            $object->update(['delete_marker' => true]);
        } else {
            // **Tanpa versioning: Hapus file langsung**
            Storage::delete($object->path);
            $object->delete();
        }

        return response()->json(['message' => 'File deleted']);
    }
}
