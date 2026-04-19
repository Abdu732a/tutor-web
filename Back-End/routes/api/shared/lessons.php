<?php
// routes/api/shared/lessons.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LessonController;

// Lesson routes (shared between students and tutors)
Route::prefix('tutorials/{tutorial}')->group(function () {
    // Debug route (temporary)
    Route::get('/debug-lessons', [LessonController::class, 'debugLessons']);
    
    // Get specific lesson (supports "first" as lesson ID)
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    
    // Get tutorial progress
    Route::get('/progress', [LessonController::class, 'progress']);
});

// Mark lesson as completed
Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete']);

// Download lesson material
Route::get('/lessons/materials/{material}/download', [LessonController::class, 'downloadMaterial']);