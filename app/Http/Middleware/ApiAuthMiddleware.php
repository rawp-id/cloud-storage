<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\Bucket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessKey = $request->header('X-Access-Key');
        $secretKey = $request->header('X-Secret-Key');

        // dd($accessKey, $secretKey);

        if (!$accessKey || !$secretKey) {
            return response()->json(['error' => 'Unauthorized. Missing keys.'], 401);
        }

        // dd($accessKey, $secretKey);

        $user = User::where('access_key', $accessKey)
            ->where('secret_key', $secretKey)
            ->first();

        // dd($user);
        
        if ($user) {
            $bucket = $request->bucket ?? $request->input('bucket') ?? $request->query('bucket') ?? $request->header('X-Bucket');
            if ($bucket) {
                $bucket = Bucket::where('name', $bucket)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$bucket) {
                    return response()->json(['error' => 'Unauthorized. Invalid bucket.'], 401);
                }
            }
            // Inject ke request
            $request->merge(['auth_type' => 'master']);
            $request->merge(['bucket' => $bucket]);
            $request->merge(['user' => $user]);
            return $next($request);
        }

        $bucket = Bucket::where('access_key', $accessKey)
            ->where('secret_key', $secretKey)
            ->first();

        if ($bucket) {
            $request->merge(['auth_type' => 'bucket']);
            $request->merge(['bucket' => $bucket]);
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized. Invalid credentials.'], 401);
    }
}
