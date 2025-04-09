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
    public function accessSignedUrl(Request $request, $bucketId, $filename)
    {
        $expiresAt = $request->query('expires');
        $signature = $request->query('signature');

        $object = ObjectStorage::where('bucket_id', $bucketId)->where('key', $filename)->first();

        // dd($object);

        if (!$expiresAt || !$signature) {
            return response()->json(['error' => 'Invalid signed URL'], 400);
        }

        if (Carbon::now()->timestamp > $expiresAt) {
            return response()->json(['error' => 'Signed URL expired'], 403);
        }

        $secretKey = env('SIGNED_URL_SECRET', 'default_secret_key');
        // dd($secretKey);
        $expectedSignature = hash_hmac('sha256', "{$filename}:{$expiresAt}", $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        if (!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response(Storage::get($object->path))->header('Content-Type', 'file');
    }

    public function showFile(Request $request, $bucket, $filename)
    {
        $bucket = Bucket::where('name', $bucket)->first();
        if (!$bucket) {
            return response()->json(['error' => 'Bucket not found'], 404);
        }
        // dd($bucket);
        // $path = "{$bucket->storage_path}/$filename";
        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->first();
        
        // dd($object);
        if(!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if (!Storage::exists($object->path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if ($object->visibility == 'private') {
            // return response()->json(['error' => 'File not found'], 404);
            return $this->accessSignedUrl($request, $bucket->id, $filename);
        }

        return response(Storage::get($object->path))->header('Content-Type', 'file');
    }

    /**
     * @OA\Post(
     *     path="/api/upload",
     *     tags={"Storage"},
     *     summary="Upload a file",
     *     description="Uploads a file to a specified bucket with optional versioning and object lock.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="visibility", type="string", enum={"public", "private"}, example="private"),
     *                 @OA\Property(property="locked_until", type="integer", example=30)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="File uploaded"),
     *             @OA\Property(property="object", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="File is locked and cannot be overwritten"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function uploadFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'visibility' => 'in:public,private',
                'locked_until' => 'integer',
                // 'key' => 'required|string|unique:objects,key,NULL,id,bucket_id,' . $request->bucket->id,
                'bucket' => 'nullable|exists:buckets,name',
            ]);

            $bucket = $request->bucket;
            // dd($bucket);
            // $bucket = Bucket::where('name', $bucket)->first();
            // if (!$bucket) {
            //     return response()->json(['error' => 'Bucket not found'], 404);
            // }

            $filename = $request->file('file')->getClientOriginalName();
            $baseKey = $filename;

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
                // $versionId = Str::uuid();
                // $path = "{$bucket->storage_path}/v{$versionId}_{$filename}";
                $versionId = (string) Str::uuid();

                // Ambil version terakhir, lalu tambah 1
                $lastVersion = ObjectStorage::where('bucket_id', $bucket->id)
                    ->where('key', $baseKey)->latest()->first();


                // dd($lastVersion);

                $versionNumber = $lastVersion ? $lastVersion->version + 1 : 1;

                $lastVersionDelete = $lastVersion ? $lastVersion->delete() : null;

                $filename = "v{$versionNumber}_{$filename}";
                $path = "{$bucket->storage_path}/{$filename}";
            } else {
                // Hapus file lama jika versioning tidak aktif
                Storage::delete($path);
                ObjectStorage::where('bucket_id', $bucket->id)
                    ->where('key', $filename)
                    ->forceDelete();
            }

            // Simpan file baru
            Storage::put($path, file_get_contents($request->file('file')));

            $lockedUntil = $request->locked_until ?? 30; // Default 30 hari lock

            $object = ObjectStorage::create([
                'bucket_id' => $bucket->id,
                'key' => $baseKey,
                'path' => $path,
                'version_id' => $bucket->versioning ? $versionId : null,
                'version' => $bucket->versioning ? $versionNumber : null,
                'locked_until' => $bucket->object_lock ? now()->addDays($lockedUntil) : null, // Default 30 hari lock
                'visibility' => $request->visibility ?? 'private',
            ]);

            return response()->json(['message' => 'File uploaded', 'object' => $object]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/signed-url/{filename}",
     *     tags={"Storage"},
     *     summary="Generate a signed URL",
     *     description="Generates a signed URL for accessing a file, with an expiration time.",
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename to generate signed URL for",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Signed URL generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="signed_url", type="string"),
     *             @OA\Property(property="expires_in", type="string", example="10 minutes")
     *         )
     *     ),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function generateSignedUrl(Request $request, $filename = null)
    {
        $filename = $request->filename ?? $filename ?? $request->input('filename') ?? $request->query('filename') ?? $request->header('filename');
        $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->first();

        if (!$object) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if ($object->visibility === 'public') {
            return response()->json(['url' => url("/storage", [
                'bucket' => $bucket->name,
                'filename' => $filename
            ])]);
        }

        $expTime = (int) max(min($request->input('expTime', 10), 60), 1); // Maks 60 menit, Min 1 menit
        // dd($expTime);
        $expiresAt = Carbon::now()->addMinutes($expTime)->timestamp;

        $secretKey = env('SIGNED_URL_SECRET', 'default_secret_key');
        // dd($secretKey);
        $signature = hash_hmac('sha256', "{$filename}:{$expiresAt}", $secretKey);

        $signedUrl = url("/storage/{$bucket->name}/{$filename}") .
            "?expires={$expiresAt}&signature={$signature}";

        return response()->json(['signed_url' => $signedUrl, 'expires_in' => $expTime . ' minutes']);
    }

    /**
     * @OA\Post(
     *     path="/api/visibility/{filename}",
     *     tags={"Storage"},
     *     summary="Set file visibility",
     *     description="Updates the visibility of a file to public or private.",
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename to update visibility",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="visibility", type="string", enum={"public", "private"}, example="public")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Visibility updated successfully"),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function setVisibility(Request $request, $filename = null)
    {
        $filename = $request->filename ?? $filename ?? $request->input('filename') ?? $request->query('filename') ?? $request->header('filename');
        $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        // Validasi
        if (!$filename) {
            return response()->json(['error' => 'Filename is required'], 400);
        }

        $request->validate(['visibility' => 'required|in:public,private']);

        // $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        $object = ObjectStorage::where('bucket_id', $bucket->id)->where('key', $filename)->firstOrFail();

        $object->visibility = $request->visibility;
        $object->save();

        return response()->json(['message' => 'Visibility updated']);
    }

    /**
     * @OA\Get(
     *     path="/api/download/{filename}",
     *     tags={"Storage"},
     *     summary="Download a file",
     *     description="Allows downloading of a stored file.",
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename to download",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="File downloaded successfully"),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function downloadFile(Request $request, $filename = null)
    {
        $filename = $request->filename ?? $filename ?? $request->input('filename') ?? $request->query('filename') ?? $request->header('filename');

        if (!$filename) {
            return response()->json(['error' => 'Filename is required'], 400);
        }

        $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        $path = "{$bucket->storage_path}/$filename";

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::download($path);
    }

    /**
     * @OA\Delete(
     *     path="/api/delete/{filename}",
     *     tags={"Storage"},
     *     summary="Delete a file",
     *     description="Deletes a file permanently.",
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename to delete",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="File deleted successfully"),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function hardDeleteFile(Request $request, $filename = null)
    {
        $filename = $request->filename ?? $filename ?? $request->input('filename') ?? $request->query('filename') ?? $request->header('filename');

        if (!$filename) {
            return response()->json(['error' => 'Filename is required'], 400);
        }

        $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        $path = "{$bucket->storage_path}/$filename";

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        Storage::delete($path);
        return response()->json(['message' => 'File deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/storage/soft-delete/{filename}",
     *     tags={"Storage"},
     *     summary="Soft delete a file",
     *     description="Marks a file as deleted without permanently removing it. If versioning is enabled, a delete marker is used; otherwise, the file is removed.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bucket", "filename"},
     *             @OA\Property(property="bucket", type="string", example="my-bucket"),
     *             @OA\Property(property="filename", type="string", example="document.pdf")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="File deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="File not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="File is locked and cannot be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="File is locked and cannot be deleted")
     *         )
     *     )
     * )
     */
    public function softDeleteFile(Request $request, $filename = null)
    {
        $filename = $request->filename ?? $filename ?? $request->input('filename') ?? $request->query('filename') ?? $request->header('filename');

        if (!$filename) {
            return response()->json(['error' => 'Filename is required'], 400);
        }

        $bucket = $request->bucket;
        // $bucket = Bucket::where('name', $bucket)->first();
        // if (!$bucket) {
        //     return response()->json(['error' => 'Bucket not found'], 404);
        // }

        // $filename = $request->filename ?? $filename;

        $object = ObjectStorage::where('bucket_id', $bucket->id)
            ->where('key', $filename)
            ->orderByDesc('created_at')
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
