<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Tutorial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LessonController extends Controller
{
    /**
     * Debug: Get lessons for a tutorial (temporary)
     */
    public function debugLessons($tutorial)
    {
        try {
            $tutorialId = $tutorial;
            $lessons = Lesson::where('tutorial_id', $tutorialId)->orderBy('order')->get();
            
            return response()->json([
                'success' => true,
                'tutorial_id' => $tutorialId,
                'lessons_count' => $lessons->count(),
                'lessons' => $lessons->map(function($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'order' => $lesson->order,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show lesson details
     */
    public function show($tutorial, $lesson)
    {
        try {
            $user = Auth::user();
            $tutorialId = $tutorial; // Route parameter
            $tutorialModel = Tutorial::findOrFail($tutorialId);

            // Handle "first" as a special lesson ID
            if ($lesson === 'first') {
                $firstLesson = Lesson::where('tutorial_id', $tutorialId)
                    ->orderBy('order')
                    ->first();
                
                if (!$firstLesson) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No lessons found for this tutorial'
                    ], 404);
                }
                
                $lessonId = $firstLesson->id;
            } else {
                $lessonId = $lesson; // Route parameter
            }

            // -----------------------------
            // Determine access level
            // -----------------------------
            $isAdminOrTutor = in_array($user->role, ['tutor', 'admin', 'super_admin']);

            // Check if student is enrolled in the course that contains this tutorial
            $isEnrolledInCourse = false;
            if ($tutorialModel->course_id) {
                $isEnrolledInCourse = $user->enrollments()
                    ->where('course_id', $tutorialModel->course_id)
                    ->exists();
            }

            // Also check direct tutorial enrollment (for backward compatibility)
            $isEnrolledInTutorial = $user->enrollments()
                ->where('tutorial_id', $tutorialId)
                ->exists();

            $hasTutorialAccess = $isAdminOrTutor || $isEnrolledInCourse || $isEnrolledInTutorial || $tutorialModel->is_free;

            if (!$hasTutorialAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this tutorial'
                ], 403);
            }

            // -----------------------------
            // Get lesson
            // -----------------------------
            $lessonModel = Lesson::where('tutorial_id', $tutorialId)
                ->where('id', $lessonId)
                ->with('materials') // Load materials
                ->firstOrFail();

            // -----------------------------
            // Check lesson accessibility
            // -----------------------------
            if (!$this->checkLessonAccess($lessonModel, $user, $tutorialId, $tutorialModel)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This lesson is locked'
                ], 403);
            }

            // -----------------------------
            // Completion status
            // -----------------------------
            $isCompleted = LessonCompletion::where('user_id', $user->id)
                ->where('lesson_id', $lessonModel->id)
                ->exists();

            // -----------------------------
            // Navigation
            // -----------------------------
            $nextLesson = Lesson::where('tutorial_id', $tutorialId)
                ->where('order', '>', $lessonModel->order)
                ->orderBy('order')
                ->first();

            $previousLesson = Lesson::where('tutorial_id', $tutorialId)
                ->where('order', '<', $lessonModel->order)
                ->orderBy('order', 'desc')
                ->first();

            // -----------------------------
            // Sidebar lessons
            // -----------------------------
            $sidebarLessons = Lesson::where('tutorial_id', $tutorialId)
                ->orderBy('order')
                ->get()
                ->map(function ($lessonItem) use ($user, $tutorialId, $tutorialModel) {
                    $isAccessible = $this->checkLessonAccess($lessonItem, $user, $tutorialId, $tutorialModel);
                    return [
                        'id' => $lessonItem->id,
                        'title' => $lessonItem->title,
                        'order' => $lessonItem->order,
                        'duration' => $lessonItem->duration,
                        'is_preview' => $lessonItem->is_preview,
                        'is_locked' => !$isAccessible, // Use calculated accessibility instead of database field
                        'is_completed' => LessonCompletion::where('user_id', $user->id)
                            ->where('lesson_id', $lessonItem->id)
                            ->exists(),
                        'is_accessible' => $isAccessible,
                    ];
                });

            // -----------------------------
            // Progress
            // -----------------------------
            $completedLessons = LessonCompletion::where('user_id', $user->id)
                ->where('tutorial_id', $tutorialId)
                ->count();

            $totalLessons = Lesson::where('tutorial_id', $tutorialId)->count();

            return response()->json([
                'success' => true,
                'lesson' => [
                    'id' => $lessonModel->id,
                    'title' => $lessonModel->title,
                    'description' => $lessonModel->description,
                    'duration' => $lessonModel->duration,
                    'order' => $lessonModel->order,
                    'video_url' => $lessonModel->video_url,
                    'content' => $lessonModel->content,
                    'is_preview' => $lessonModel->is_preview,
                    'is_locked' => $lessonModel->is_locked,
                    'is_completed' => $isCompleted,
                    'created_at' => $lessonModel->created_at,
                    'updated_at' => $lessonModel->updated_at,
                    'materials' => $lessonModel->materials->map(function($material) {
                        return [
                            'id' => $material->id,
                            'original_name' => $material->original_name,
                            'mime_type' => $material->mime_type,
                            'size_kb' => $material->size_kb,
                            'download_url' => url('/api/lessons/materials/' . $material->id . '/download')
                        ];
                    })
                ],
                'is_accessible' => true,
                'tutorial' => [
                    'id' => $tutorialModel->id,
                    'title' => $tutorialModel->title,
                    'instructor' => $tutorialModel->instructor,
                    'category' => $tutorialModel->category,
                ],
                'navigation' => [
                    'next_lesson' => $nextLesson ? [
                        'id' => $nextLesson->id,
                        'title' => $nextLesson->title,
                        'order' => $nextLesson->order,
                        'is_accessible' => $this->checkLessonAccess($nextLesson, $user, $tutorialId, $tutorialModel),
                    ] : null,
                    'previous_lesson' => $previousLesson ? [
                        'id' => $previousLesson->id,
                        'title' => $previousLesson->title,
                        'order' => $previousLesson->order,
                        'is_accessible' => $this->checkLessonAccess($previousLesson, $user, $tutorialId, $tutorialModel),
                    ] : null,
                ],
                'sidebar_lessons' => $sidebarLessons,
                'progress' => [
                    'completed_lessons' => $completedLessons,
                    'total_lessons' => $totalLessons,
                    'percentage' => $totalLessons > 0
                        ? round(($completedLessons / $totalLessons) * 100, 2)
                        : 0,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Lesson show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lesson'
            ], 500);
        }
    }

    /**
     * Download lesson material
     */
    public function downloadMaterial($materialId)
    {
        try {
            $user = Auth::user();
            $material = \App\Models\LessonMaterial::findOrFail($materialId);
            $lesson = $material->lesson;
            $tutorial = $lesson->tutorial;

            // Check if user has access to this tutorial
            $isAdminOrTutor = in_array($user->role, ['tutor', 'admin', 'super_admin']);
            $isEnrolledInCourse = false;
            if ($tutorial->course_id) {
                $isEnrolledInCourse = $user->enrollments()
                    ->where('course_id', $tutorial->course_id)
                    ->exists();
            }
            $isEnrolledInTutorial = $user->enrollments()
                ->where('tutorial_id', $tutorial->id)
                ->exists();
            $hasTutorialAccess = $isAdminOrTutor || $isEnrolledInCourse || $isEnrolledInTutorial || $tutorial->is_free;

            if (!$hasTutorialAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this material'
                ], 403);
            }

            // Check if file exists
            $filePath = storage_path('app/public/' . $material->file_path);
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Return file download
            return response()->download($filePath, $material->original_name);

        } catch (\Exception $e) {
            Log::error('Material download error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download material'
            ], 500);
        }
    }

    /**
     * Mark lesson as completed
     */
    public function complete(Request $request, $lessonId)
    {
        try {
            $user = Auth::user();
            $lesson = Lesson::findOrFail($lessonId);

            LessonCompletion::firstOrCreate([
                'user_id' => $user->id,
                'lesson_id' => $lessonId,
                'tutorial_id' => $lesson->tutorial_id,
            ], [
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lesson marked as completed'
            ]);

        } catch (\Exception $e) {
            Log::error('Lesson completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark lesson as completed'
            ], 500);
        }
    }

    /**
     * Lesson access rules (SINGLE SOURCE OF TRUTH)
     */
    private function checkLessonAccess($lesson, $user, $tutorialId, $tutorial = null)
    {
        // Admins & tutors have full access
        if (in_array($user->role, ['tutor', 'admin', 'super_admin'])) {
            return true;
        }

        // Get tutorial if not provided
        if (!$tutorial) {
            $tutorial = Tutorial::find($tutorialId);
        }

        // Check if student is enrolled in the course that contains this tutorial
        $isEnrolledInCourse = false;
        if ($tutorial && $tutorial->course_id) {
            $isEnrolledInCourse = $user->enrollments()
                ->where('course_id', $tutorial->course_id)
                ->exists();
        }

        // Also check direct tutorial enrollment (for backward compatibility)
        $isEnrolledInTutorial = $user->enrollments()
            ->where('tutorial_id', $tutorialId)
            ->exists();

        // Must be enrolled to access any lessons
        if (!($isEnrolledInCourse || $isEnrolledInTutorial)) {
            // Preview lessons are accessible to everyone
            if ($lesson->is_preview) {
                return true;
            }
            return false;
        }

        // Preview lessons are always accessible to enrolled students
        if ($lesson->is_preview) {
            return true;
        }

        // First lesson (order 1) is always unlocked for enrolled students
        if ($lesson->order == 1) {
            return true;
        }

        // For subsequent lessons, check if previous lesson is completed
        $previousLesson = Lesson::where('tutorial_id', $tutorialId)
            ->where('order', $lesson->order - 1)
            ->first();

        if ($previousLesson) {
            $isPreviousCompleted = LessonCompletion::where('user_id', $user->id)
                ->where('lesson_id', $previousLesson->id)
                ->exists();

            return $isPreviousCompleted;
        }

        // If no previous lesson found, allow access (shouldn't happen normally)
        return true;
    }
}
