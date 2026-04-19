<?php

use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // User Management
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::put('/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
    Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser']);
    Route::post('/users/{user}/activate', [AdminController::class, 'activateUser']);
    Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    
    // Tutor Onboarding
    Route::get('/pending-tutors', [AdminController::class, 'pendingTutors']);
    Route::post('/tutors/{tutor}/approve', [AdminController::class, 'approveTutor']);
    Route::post('/tutors/{tutor}/reject', [AdminController::class, 'rejectTutor']);
    
    // Class Management
    Route::get('/classes', [AdminController::class, 'classes']);
    Route::post('/classes', [AdminController::class, 'createClass']);
    
    // Session Reports
    Route::get('/pending-reports', [AdminController::class, 'pendingReports']);
    Route::post('/reports/{report}/approve', [AdminController::class, 'approveReport']);
    Route::get('/tutors', function (\Illuminate\Http\Request $request) {
        $courseId = $request->get('course_id');
        
        if ($courseId) {
            // If course_id is provided, do filtering
            $course = \App\Models\Course::with('category.parent')->find($courseId);
            
            if ($course && $course->category) {
                $subcategoryName = $course->category->name;
                $parentCategoryName = $course->category->parent ? $course->category->parent->name : null;
                
                $tutors = \App\Models\User::where('role', 'tutor')
                    ->where('status', 'active')
                    ->whereHas('tutor', function ($tutorQuery) {
                        $tutorQuery->where('is_verified', true);
                    })
                    ->whereHas('tutor.subjects', function ($subjectQuery) use ($subcategoryName, $parentCategoryName) {
                        $subjectQuery->where('subject_name', 'like', "%{$subcategoryName}%");
                        $subjectQuery->orWhere('specialization', 'like', "%{$subcategoryName}%");
                        
                        if ($parentCategoryName) {
                            $subjectQuery->orWhere('subject_name', 'like', "%{$parentCategoryName}%");
                            $subjectQuery->orWhere('specialization', 'like', "%{$parentCategoryName}%");
                        }
                    })
                    ->select('id', 'name', 'email')
                    ->get();
                
                return response()->json([
                    'success' => true,
                    'tutors' => $tutors,
                    'filtered' => true,
                    'course_info' => [
                        'title' => $course->title,
                        'category' => $subcategoryName,
                        'parent_category' => $parentCategoryName,
                    ],
                    'total_filtered' => $tutors->count()
                ]);
            }
        }
        
        // Default: return all tutors
        $tutors = \App\Models\User::where('role', 'tutor')
            ->where('status', 'active')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json([
            'success' => true,
            'tutors' => $tutors,
            'filtered' => false,
            'total_all' => $tutors->count()
        ]);
    });
});

