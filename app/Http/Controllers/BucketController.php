<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bucket;
use Illuminate\Support\Str;

class BucketController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/buckets",
     *     tags={"Bucket"},
     *     summary="Create a new bucket",
     *     description="Creates a new bucket with a unique name and generates access keys.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="my-bucket"),
     *             @OA\Property(property="visibility", type="string", enum={"public", "private"}, example="public"),
     *             @OA\Property(property="versioning", type="boolean", example=false),
     *             @OA\Property(property="object_lock", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bucket created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bucket created successfully"),
     *             @OA\Property(property="access_key", type="string", example="abcdef1234567890"),
     *             @OA\Property(property="secret_key", type="string", example="abcdef1234567890abcdef1234567890abcdef12")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function createBucket(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:buckets,name',
            'visibility' => 'in:public,private',
            'versioning' => 'boolean',
            'object_lock' => 'boolean'
        ]);

        // dd($request->all());

        $bucket = Bucket::create([
            'user_id' => $request->user->id,
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

    /**
     * @OA\Get(
     *     path="/api/buckets",
     *     tags={"Bucket"},
     *     summary="Get list of all buckets",
     *     description="Retrieve a list of all created buckets.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="my-bucket"),
     *                 @OA\Property(property="storage_path", type="string", example="storage/my-bucket"),
     *                 @OA\Property(property="access_key", type="string", example="abcdef1234567890"),
     *                 @OA\Property(property="secret_key", type="string", example="abcdef1234567890abcdef1234567890abcdef12")
     *             )
     *         )
     *     )
     * )
     */
    public function listBuckets(Request $request)
    {
        $bucket = Bucket::where('user_id', $request->user->id)->get();
        if ($bucket->isEmpty()) {
            return response()->json(['message' => 'No buckets found'], 404);
        }
        return response()->json([
            'message' => 'Buckets retrieved successfully',
            'buckets' => $bucket
        ]);
    }

    public function deleteBucket(Request $request, $id)
    {
        $bucket = Bucket::find($id);
        if (!$bucket) {
            return response()->json(['message' => 'Bucket not found'], 404);
        }

        $bucket->delete();

        return response()->json(['message' => 'Bucket deleted successfully']);
    }
    public function updateBucket(Request $request, $id)
    {
        $bucket = Bucket::find($id);
        if (!$bucket) {
            return response()->json(['message' => 'Bucket not found'], 404);
        }

        $request->validate([
            'name' => 'required|unique:buckets,name,' . $bucket->id,
            'visibility' => 'in:public,private',
            'versioning' => 'boolean',
            'object_lock' => 'boolean'
        ]);

        $bucket->update($request->all());

        return response()->json(['message' => 'Bucket updated successfully']);
    }
    public function getBucket(Request $request, $id)
    {
        $bucket = Bucket::find($id);
        if (!$bucket) {
            return response()->json(['message' => 'Bucket not found'], 404);
        }

        return response()->json(['bucket' => $bucket]);
    }
    public function getBucketByName(Request $request, $name)
    {
        $bucket = Bucket::where('name', $name)->first();
        if (!$bucket) {
            return response()->json(['message' => 'Bucket not found'], 404);
        }

        return response()->json(['bucket' => $bucket]);
    }
}
