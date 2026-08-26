<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->email,
        ]);

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ], 201);
    }

    // Kept for the old Next.js app (discount repo), which stays deployed
    // side by side with this one until cutover (see the migration plan's
    // Phase 7 parallel-run step) and still calls this via its NextAuth
    // callback. Safe to delete once that app is retired.
    public function oauth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nextauth_token' => 'required|string',
            'provider' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $nextAuthSecret = config('services.nextauth.secret');

        if (!$nextAuthSecret) {
            return response()->json([
                'error' => 'NEXTAUTH_SECRET not configured'
            ], 500);
        }

        try {
            $decoded = JWT::decode($request->nextauth_token, new Key($nextAuthSecret, 'HS256'));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid NextAuth token',
                'message' => $e->getMessage()
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->update([
                'name' => $request->name,
                'email_verified_at' => now(),
            ]);
        } else {
            $user = User::create([
                'email' => $request->email,
                'name' => $request->name,
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'message' => 'Token refresh not implemented yet'
        ], 501);
    }
}

