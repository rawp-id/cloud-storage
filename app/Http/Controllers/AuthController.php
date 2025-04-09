<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);


        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'access_key' => bin2hex(random_bytes(16)),
            'secret_key' => bin2hex(random_bytes(32)),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            // 'user' => $user,
            'access_key' => $user->access_key,
            'access_token' => $user->secret_key,
        ], 201);
    }
}
