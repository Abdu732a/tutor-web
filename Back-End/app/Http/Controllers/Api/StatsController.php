<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\Tutorial;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * Get homepage statistics (public)
     */
    public function getHomepageStats(Request $request)
    {
        try {
            // Get actual counts from database
            $totalStudents = User::where('role', 'student')
                ->where('status', 'active')
                ->count();
            
            $totalCourses = Course::where('is_active', true)->count();
            
            $totalTutorials = Tutorial::where('status', 'published')->count();
            
            $stats = [
                'total_students' => $totalStudents,
                'total_courses' => $totalCourses,
                'total_tutorials' => $totalTutorials
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Homepage statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Homepage stats error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}