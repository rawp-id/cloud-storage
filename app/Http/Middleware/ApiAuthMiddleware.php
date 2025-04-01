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
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::where('access_key', $accessKey)
            ->where('secret_key', $secretKey)
            ->first() ?? null;

        dd($user);

        $bucket = Bucket::where('access_key', $accessKey)
            ->where('secret_key', $secretKey)
            ->first() ?? null;

        if (!$user || !$bucket) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // if (!$bucket) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }

        $request->merge(['bucket' => $bucket]);
        $request->merge(['user' => $user]);
        return $next($request);
    }
}
