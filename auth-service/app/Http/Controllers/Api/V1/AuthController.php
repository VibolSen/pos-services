<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $loginInput = trim($request->email);

        $query = User::where('email', $loginInput);
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
            $query->orWhere('phone', $loginInput);
        }
        $user = $query->first();


        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials (email or phone number) are incorrect.'],
            ]);
        }

        $token = $user->createToken('pos_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string',
        ]);

        $email = $request->email;
        $phone = $request->phone;

        if (!$email && !$phone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please provide either an email address or a phone number.'
            ], 422);
        }

        if ($email && User::where('email', $email)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This email address is already registered.'
            ], 422);
        }

        if ($phone && User::where('phone', $phone)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This phone number is already registered.'
            ], 422);
        }

        if (!$email && $phone) {
            $cleanedPhone = preg_replace('/[^0-9]/', '', $phone);
            $email = $cleanedPhone . '@phone.codebridges.com';
        }

        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $request->name,
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'phone' => $phone,
        ]);

        $token = $user->createToken('pos_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User account registered successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
