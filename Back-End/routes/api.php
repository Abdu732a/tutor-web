<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ChapaController;
use Illuminate\Support\Facades\Mail;
use App\Mail\TutorWelcomeEmail;
use App\Mail\TutorRejectionEmail;
use App\Models\Tutor;
use App\Models\User;

// ============================
// 🚀 Load All Route Files
// ============================

// Test route (no auth required)
Route::get('/test-connection', function () {
    return response()->json([
        'success' => true,
        'message' => 'Connection working',
        'timestamp' => now()
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});

// Test lesson endpoint without auth
Route::get('/test-lesson/{tutorial}', function ($tutorial) {
    try {
        $lessons = \App\Models\Lesson::where('tutorial_id', $tutorial)->orderBy('order')->get();
        $firstLesson = $lessons->first();
        
        return response()->json([
            'success' => true,
            'tutorial_id' => $tutorial,
            'lessons_count' => $lessons->count(),
            'first_lesson' => $firstLesson ? [
                'id' => $firstLesson->id,
                'title' => $firstLesson->title,
                'order' => $firstLesson->order
            ] : null,
            'all_lessons' => $lessons->map(function($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'order' => $lesson->order
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

// Public routes (no authentication required)
require __DIR__ . '/api/public.php';
require __DIR__ . '/api/shared/courses.php';
Route::get('/tutorials/{id}', [App\Http\Controllers\Api\TutorialController::class, 'show']);

// Authentication routes
require __DIR__ . '/api/auth.php';

// ============================
// 🔐 Protected Routes (Authenticated Users)
// ============================

Route::middleware(['auth:sanctum'])->group(function () {

    require __DIR__ . '/api/payments/chapa.php';
    
    // Common authenticated user routes
    require __DIR__ . '/api/shared/user.php';
    require __DIR__ . '/api/shared/lessons.php';
    // In your main api.php, inside the auth:sanctum middleware group
    require __DIR__ . '/api/shared/profile.php';
    
    // Student routes (only these are created so far)
    require __DIR__ . '/api/student/dashboard.php';
    require __DIR__ . '/api/student/profile.php';
    require __DIR__ . '/api/student/attendance.php';
    require __DIR__ . '/api/student/enrollment.php';
    require __DIR__ . '/api/student/individual-requests.php';
    // require __DIR__ . '/api/student/tutorials.php';
    // require __DIR__ . '/api/student/finance.php';

    
    // Tutor routes
    require __DIR__ . '/api/tutor/dashboard.php';
    require __DIR__ . '/api/tutor/profile.php';
    require __DIR__ . '/api/tutor/courses.php';
    require __DIR__ . '/api/tutor/attendance.php';
    require __DIR__ . '/api/tutor/students.php';
    require __DIR__ . '/api/tutor/tutorials.php'; 
    // require __DIR__ . '/api/tutor/finance.php';

    
    // Messaging routes
    require __DIR__ . '/api/messages/conversations.php';
    require __DIR__ . '/api/messages/messages.php';
    require __DIR__ . '/api/messages/announcements.php';
    
    // Shared routes (tutorial sessions)
  require __DIR__ . '/api/shared/Sessions.php';
    
    // Admin routes
    require __DIR__ . '/api/admin/dashboard.php';
    require __DIR__ . '/api/admin/users.php';
    require __DIR__ . '/api/admin/classes.php';
    require __DIR__ . '/api/admin/courses.php';
    require __DIR__ . '/api/admin/individual-requests.php';
    require __DIR__ . '/api/admin/tutorials.php';
    require __DIR__ . '/api/admin/student-tutor-assignments.php';
    require __DIR__ . '/api/admin/attendance.php';
    require __DIR__ . '/api/admin/communication.php';
    require __DIR__ . '/api/admin/tutor_approvals.php';
    require __DIR__ . '/api/admin/email_queue.php';

    
    
    Route::get('/admin/categories-tree', [App\Http\Controllers\Api\CategoryController::class, 'adminTree']);
    // TEMPORARY: Comment out other routes until we create them
    /*
    // Super Admin routes
    require __DIR__ . '/api/super-admin/dashboard.php';
    require __DIR__ . '/api/super-admin/system.php';
    
    // Staff routes (if needed)
    require __DIR__ . '/api/staff.php';
    */
});

Route::middleware('auth:sanctum')->group(function () {
    // Main payment flow - using ChapaController for consistency
    Route::post('/payment/initialize', [ChapaController::class, 'initialize']);
    Route::get('/payment/verify/{tx_ref}', [ChapaController::class, 'verify'])
        ->name('payment.verify');
    Route::get('/payment/status', [ChapaController::class, 'status']);
    Route::get('/payment/auto-verify', [ChapaController::class, 'autoVerify']);
    
    // Course selection and payment status - using PaymentController
    Route::post('/payment/select-course', [PaymentController::class, 'updateSelectedCourse']);
    Route::get('/student/payment-status', [PaymentController::class, 'getPaymentStatus']);
    Route::get('/payment/available-courses', [PaymentController::class, 'getAvailableCourses']);
});

// ... existing requires ...

// Admin routes group (add this if not already present, or append to existing admin group)
Route::middleware('auth:sanctum')->group(function () {
    // Your new route here
    Route::get('/admin/tutorials', [\App\Http\Controllers\Api\AdminController::class, 'getTutorials']);

    // If you have other loose admin routes, move them here too
});

