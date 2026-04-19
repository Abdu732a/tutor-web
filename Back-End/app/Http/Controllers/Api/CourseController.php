<?php
// app/Http/Controllers/Api/CourseController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Category;
use App\Models\ClassRoom;
use App\Models\StudentTutorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
{
    try {
        $query = Course::with(['category', 'tutors'])  // Always load subcategory and tutors
            ->withCount(['tutorials' => function ($q) {
                $q->where('status', 'published');
            }]);

        // Filter by subcategory ID (new way)
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Optional: keep old category slug filter if you still need it (fallback)
        if ($request->has('category')) {
            $slug = $request->category;
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        // Your other filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        // For admin, default to higher per_page and allow getting all
        $perPage = $request->get('per_page', 25); // Increased from 10 to 25
        
        // Allow getting all courses for admin
        if ($request->get('all') === 'true' || $request->get('per_page') === 'all') {
            $courses = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'All courses retrieved successfully',
                'data' => $courses,
                'total' => $courses->count()
            ]);
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully',
            'data' => $courses
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve courses',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function publicIndex(Request $request)
{
    try {
        $query = Course::where('is_active', true)
            ->with(['category', 'tutors'])
            ->withCount(['tutorials' => function ($q) {
                $q->where('status', 'published');
            }]);

        // Filter for featured courses if requested
        if ($request->featured || $request->has('featured')) {
            $query->where('is_featured', true);
        }

        // For featured endpoint, don't paginate - just get the courses directly
        if ($request->path() === 'api/courses/featured' || $request->featured || $request->has('featured')) {
            $courses = $query->orderBy('created_at', 'desc')
                ->take(6) // Limit to 6 for homepage featured section
                ->get()
                ->map(function ($course) {
                    // Calculate actual student enrollment count
                    $studentCount = \App\Models\Enrollment::whereHas('tutorial', function ($q) use ($course) {
                        $q->where('course_id', $course->id);
                    })->count();
                    
                    // Alternative: count from payments table if that's more accurate
                    $paymentCount = \App\Models\Payment::where('course_id', $course->id)
                        ->where('status', 'completed')
                        ->count();
                    
                    // Use the higher count (some students might have paid but not enrolled yet)
                    $actualStudentCount = max($studentCount, $paymentCount);
                    
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'description' => $course->description,
                        'category' => $course->category ? $course->category->name : 'General',
                        'duration_hours' => $course->duration_hours,
                        'price_group' => $course->price_group,
                        'price_individual' => $course->price_individual,
                        'students' => $actualStudentCount, // Real student count
                        'rating' => 4.5, // Default rating for now
                        'image' => null, // You can add image field later
                        'is_featured' => $course->is_featured ?? false,
                        'tutorials_count' => $course->tutorials_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'courses' => $courses // Use 'courses' key for featured
            ]);
        }

        // For regular courses endpoint, use pagination
        $perPage = $request->get('per_page', 10);
        $courses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully',
            'data' => $courses
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch courses',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
 * Store a newly created course.
 */
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title'             => 'required|string|max:255',
        'description'       => 'nullable|string',
        'category_id'       => 'required|exists:categories,id',  // ← changed to subcategory ID
        'duration_hours'    => 'nullable|integer|min:1',
        'price_group'       => 'nullable|numeric|min:0',
        'price_individual'  => 'nullable|numeric|min:0',
        'is_active'         => 'boolean',
        'curriculum'        => 'nullable|array',
        'learning_outcomes' => 'nullable|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        // Optional: Ensure it's a subcategory
        $category = Category::findOrFail($request->category_id);
        if ($category->level !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a subcategory (not top-level category)'
            ], 422);
        }

        $course = Course::create([
            'title'             => $request->title,
            'description'       => $request->description,
            'category_id'       => $request->category_id,  // ← now subcategory ID
            'duration_hours'    => $request->duration_hours,
            'price_group'       => $request->price_group,
            'price_individual'  => $request->price_individual,
            'curriculum'        => $request->curriculum ?? [],
            'learning_outcomes' => $request->learning_outcomes ?? [],
            'is_active'         => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data'    => $course->load('category', 'tutors')  // include subcategory info
        ], 201);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create course',
            'error'   => $e->getMessage()
        ], 500);
    }
}

/**
 * Update the specified course.
 */
public function update(Request $request, $id)
{
    $course = Course::find($id);

    if (!$course) {
        return response()->json([
            'success' => false,
            'message' => 'Course not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'title'             => 'sometimes|required|string|max:255',
        'description'       => 'nullable|string',
        'category_id'       => 'sometimes|required|exists:categories,id',
        'duration_hours'    => 'nullable|integer|min:1',
        'price_group'       => 'nullable|numeric|min:0',
        'price_individual'  => 'nullable|numeric|min:0',
        'is_active'         => 'boolean',
        'curriculum'        => 'nullable|array',
        'learning_outcomes' => 'nullable|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        if ($request->has('category_id')) {
            $category = Category::findOrFail($request->category_id);
            if ($category->level !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a subcategory'
                ], 422);
            }
        }

        $course->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'data'    => $course->load('category' , 'tutors')  // include subcategory info
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update course',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function show($id)
{
    $course = Course::with([
        'tutors',
        'tutorials' => function ($query) {
            $query->where('status', 'published') // only published ones visible to public/students
                  ->withCount('lessons')
                  ->with(['lessons' => function ($q) {
                     $q->orderBy('order');  // load ALL lessons, sorted by order
              // Remove this line or comment it out:
              // $q->where('is_preview', true);
                  }]);
        }
    ])->findOrFail($id);

    // Optional: if student is logged in, load their enrollment status & progress
    if (auth()->check()) {
        $user = auth()->user();
        $enrolled = $course->enrollments()->where('user_id', $user->id)->exists();
        $course->is_enrolled = $enrolled;

        // Add preview logic per tutorial if needed
        $course->tutorials->each(function ($tutorial) use ($user, $enrolled) {
            if (!$enrolled) {
                $tutorial->lessons = $tutorial->lessons; // already filtered to preview only
            } else {
                // Load all lessons for enrolled user
                $tutorial->load(['lessons' => fn($q) => $q->orderBy('order')]);
            }
        });
    }

    return response()->json([
        'success' => true,
        'message' => 'Course retrieved successfully',
        'data' => $course
    ]);
}

// In your CourseController or new ClassController
public function createClassFromAssignments(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'course_id' => 'required|exists:courses,id',
        'tutor_id' => 'required|exists:users,id,role,tutor',
        'batch_name' => 'nullable|string',
        'max_capacity' => 'required|integer|min:1',
        'schedule' => 'required|string',
        'start_date' => 'required|date',
        'end_date' => 'nullable|date',
        'price' => 'nullable|numeric|min:0',
        'level' => 'required|in:Beginner,Intermediate,Advanced',
        'assignment_ids' => 'required|array',
        'assignment_ids.*' => 'exists:student_tutor_assignments,id'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        // Create the class
        $class = ClassRoom::create([
            'title' => $request->title,
            'description' => $request->description,
            'course_id' => $request->course_id,
            'tutor_id' => $request->tutor_id,
            'batch_name' => $request->batch_name,
            'enrollment_code' => 'CLASS-' . strtoupper(Str::random(8)),
            'max_capacity' => $request->max_capacity,
            'current_enrollment' => count($request->assignment_ids),
            'schedule' => $request->schedule,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'price' => $request->price,
            'level' => $request->level,
            'status' => 'upcoming'
        ]);

        // Update assignments to belong to this class
        StudentTutorAssignment::whereIn('id', $request->assignment_ids)
            ->update([
                'class_id' => $class->id,
                'status' => 'active'
            ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class->load(['course', 'tutor'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to create class',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function addAssignmentsToClass(Request $request, $classId)
{
    $class = ClassRoom::findOrFail($classId);

    $validator = Validator::make($request->all(), [
        'assignment_ids' => 'required|array',
        'assignment_ids.*' => 'exists:student_tutor_assignments,id'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Check capacity
        $newTotal = $class->current_enrollment + count($request->assignment_ids);
        if ($newTotal > $class->max_capacity) {
            return response()->json([
                'success' => false,
                'message' => 'Exceeds maximum capacity'
            ], 422);
        }

        // Update assignments
        StudentTutorAssignment::whereIn('id', $request->assignment_ids)
            ->update([
                'class_id' => $class->id,
                'status' => 'active'
            ]);

        // Update class enrollment count
        $class->current_enrollment = $newTotal;
        $class->save();

        return response()->json([
            'success' => true,
            'message' => 'Assignments added to class successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to add assignments',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Remove the specified course (soft delete).
     */
    public function destroy($id)
    {
        $course = Course::find($id);
        
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        }

        try {
            $course->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Course deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete course',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all categories.
     */
    public function categories()
    {
        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => Course::getCategories()
        ]);
    }

    public function assignTutors(Request $request, $id)
{
    $course = Course::with('category.parent')->findOrFail($id);

    $validator = Validator::make($request->all(), [
        'tutor_ids' => 'required|array',
        'tutor_ids.*' => 'exists:users,id,role,tutor',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Optional: Validate that selected tutors match course category
    if ($course->category) {
        $subcategoryName = $course->category->name;
        $parentCategoryName = $course->category->parent ? $course->category->parent->name : null;
        
        $validTutorIds = User::where('role', 'tutor')
            ->where('status', 'active')
            ->whereHas('tutor.subjects', function ($subjectQuery) use ($subcategoryName, $parentCategoryName) {
                $subjectQuery->where('subject_name', 'like', "%{$subcategoryName}%");
                $subjectQuery->orWhere('specialization', 'like', "%{$subcategoryName}%");
                
                if ($parentCategoryName) {
                    $subjectQuery->orWhere('subject_name', 'like', "%{$parentCategoryName}%");
                    $subjectQuery->orWhere('specialization', 'like', "%{$parentCategoryName}%");
                }
            })
            ->pluck('id')
            ->toArray();
        
        $invalidTutors = array_diff($request->tutor_ids, $validTutorIds);
        if (!empty($invalidTutors)) {
            $invalidTutorNames = User::whereIn('id', $invalidTutors)->pluck('name')->toArray();
            return response()->json([
                'success' => false,
                'message' => 'Some selected tutors do not match the course category',
                'invalid_tutors' => $invalidTutorNames,
                'course_category' => $subcategoryName,
                'parent_category' => $parentCategoryName
            ], 422);
        }
    }

    // Sync tutors (many-to-many)
    $course->tutors()->sync($request->tutor_ids);

    return response()->json([
        'success' => true,
        'message' => 'Tutors assigned successfully',
        'assigned_count' => count($request->tutor_ids)
    ]);
}

    /**
     * Get available tutors for a course (for admin assignment).
     * Filters tutors based on course category and subcategory matching.
     */
    public function getAvailableTutors($courseId)
    {
        try {
            $course = Course::with('category.parent')->find($courseId);
            
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }
            
            if (!$course->category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course has no category assigned'
                ], 400);
            }
            
            // Get category names for matching
            $subcategoryName = $course->category->name; // e.g., "AI"
            $parentCategoryName = $course->category->parent ? $course->category->parent->name : null; // e.g., "Programming"
            
            // Find tutors whose subjects match the course category/subcategory
            $tutors = User::where('role', 'tutor')
                ->where('status', 'active')
                ->whereHas('tutor', function ($tutorQuery) {
                    $tutorQuery->where('is_verified', true);
                })
                ->whereHas('tutor.subjects', function ($subjectQuery) use ($subcategoryName, $parentCategoryName) {
                    // Match by subcategory name (exact or partial)
                    $subjectQuery->where('subject_name', 'like', "%{$subcategoryName}%");
                    
                    // Also match by specialization field
                    $subjectQuery->orWhere('specialization', 'like', "%{$subcategoryName}%");
                    
                    // If there's a parent category, also match that
                    if ($parentCategoryName) {
                        $subjectQuery->orWhere('subject_name', 'like', "%{$parentCategoryName}%");
                        $subjectQuery->orWhere('specialization', 'like', "%{$parentCategoryName}%");
                    }
                })
                ->with([
                    'tutor.subjects' => function ($query) use ($subcategoryName, $parentCategoryName) {
                        // Only load relevant subjects for display
                        $query->where('subject_name', 'like', "%{$subcategoryName}%");
                        if ($parentCategoryName) {
                            $query->orWhere('subject_name', 'like', "%{$parentCategoryName}%");
                            $query->orWhere('specialization', 'like', "%{$parentCategoryName}%");
                        }
                    }
                ])
                ->select('id', 'name', 'email', 'phone')
                ->get()
                ->map(function ($tutor) {
                    return [
                        'id' => $tutor->id,
                        'name' => $tutor->name,
                        'email' => $tutor->email,
                        'phone' => $tutor->phone,
                        'subjects' => $tutor->tutor->subjects->map(function ($subject) {
                            return [
                                'subject_name' => $subject->subject_name,
                                'specialization' => $subject->specialization,
                                'level' => $subject->level
                            ];
                        }),
                        'experience_years' => $tutor->tutor->experience_years ?? 0,
                        'hourly_rate' => $tutor->tutor->hourly_rate ?? 0,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'message' => 'Available tutors retrieved successfully',
                'course_info' => [
                    'title' => $course->title,
                    'category' => $subcategoryName,
                    'parent_category' => $parentCategoryName,
                ],
                'matching_criteria' => [
                    'subcategory' => $subcategoryName,
                    'parent_category' => $parentCategoryName,
                ],
                'data' => $tutors,
                'total_count' => $tutors->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available tutors',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}