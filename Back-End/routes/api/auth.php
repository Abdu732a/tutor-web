<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

// ====================
// 🔄 NEW Unified Registration System
// ====================
Route::post('/register', function (\Illuminate\Http\Request $request) {
    // Route to appropriate controller based on user_type
    $userType = $request->input('user_type', 'student');
    
    return match($userType) {
        'tutor' => app()->make(\App\Http\Controllers\Auth\TutorAuthController::class)->register($request),
        'student' => app()->make(\App\Http\Controllers\Auth\StudentAuthController::class)->register($request),
        default => response()->json([
            'success' => false,
            'message' => 'Invalid user type. Use "student" or "tutor".'
        ], 422)
    };
});

// ====================
// 🔄 EXISTING Routes (backward compatibility)
// ====================
Route::post('/auth/register/student', [StudentAuthController::class, 'register']);
Route::post('/auth/register/tutor', [App\Http\Controllers\Auth\TutorAuthController::class, 'register']);

// ====================
// 🔐 Authentication
// ====================
Route::post('/login', [LoginController::class, 'login']);

// ====================
// 🔑 Password Reset
// ====================
Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetEmail']);
Route::post('/auth/verify-reset-token', [PasswordResetController::class, 'verifyResetToken']);
Route::post('/auth/reset-password', [PasswordResetController::class, 'resetPassword']);

// ====================
// 📧 Email Verification Routes
// ====================
Route::post('/auth/email/check-status', [EmailVerificationController::class, 'checkStatus']);
Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend']);
Route::post('/auth/email/verify', [EmailVerificationController::class, 'verify']); 
Route::get('/auth/email/verify/{token}', [EmailVerificationController::class, 'verify']);
Route::post('/auth/email/resend-verification', [EmailVerificationController::class, 'resend']);

// ====================
// 📄 Public Resources
// ====================
Route::get('/auth/tutors/{tutor}/degree-photo', [App\Http\Controllers\Auth\TutorAuthController::class, 'getDegreePhoto']);

// ====================
// 🧪 Testing Endpoints
// ====================
Route::post('/auth/test-file-upload', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::info('Test File Upload', [
        'all_data' => $request->all(),
        'files' => $request->allFiles(),
        'has_degreePhoto' => $request->hasFile('degreePhoto'),
        'headers' => $request->headers->all()
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Test endpoint working',
        'data_received' => $request->all(),
        'files_received' => array_keys($request->allFiles()),
        'has_degree_photo' => $request->hasFile('degreePhoto'),
    ]);
});

// ====================
// ⚠️ Unauthenticated Response
// ====================
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated. Please login first.'
    ], 401);
})->name('login');