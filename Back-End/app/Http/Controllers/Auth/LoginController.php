<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // 🔥 Load only what we need (with eager loading)
    $user = User::with(['tutor', 'student'])
        ->where('email', $request->email)
        ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid credentials'],
        ]);
    }

    // Email verification
    if (!$user->isEmailVerified()) {
        throw ValidationException::withMessages([
            'email' => 'Please verify your email first.',
        ]);
    }

    // Status check
    if ($user->isSuspended()) {
        throw ValidationException::withMessages([
            'email' => 'Account suspended.',
        ]);
    }

    // Tutor checks (LIGHT version)
    if ($user->isTutor()) {
        if (!$user->tutor) {
            throw ValidationException::withMessages([
                'email' => 'Complete tutor profile first.',
            ]);
        }

        if ($user->tutor->is_verified == 0) {
            throw ValidationException::withMessages([
                'email' => 'Waiting for admin approval.',
            ]);
        }
    }

    // Student check
    if ($user->isStudent() && !$user->isActive()) {
        throw ValidationException::withMessages([
            'email' => 'Account not active.',
        ]);
    }

    // 🔥 Create token
    $token = $user->createToken('auth-token')->plainTextToken;

    // 🔥 Update login time (non-blocking style)
    $user->last_login_at = now();
    $user->saveQuietly();

    // 🔥 Return SIMPLE response (no heavy relations)
    return response()->json([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]
    ]);
}
}