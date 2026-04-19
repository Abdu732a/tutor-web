<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            // Get categories with actual tutorial counts from database relationships
            $categories = Category::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    // Count tutorials through courses relationship: tutorials -> courses -> categories
                    $tutorialCount = \App\Models\Tutorial::whereHas('course', function ($query) use ($category) {
                        $query->where('category_id', $category->id);
                    })->where('status', 'published')->count();
                    
                    // Also count courses for this category
                    $courseCount = \App\Models\Course::where('category_id', $category->id)->count();
                    
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'icon_name' => $category->icon ?? 'BookOpen',
                        'tutorial_count' => $tutorialCount + $courseCount, // Combined count
                    ];
                });

            return response()->json([
                'success' => true,
                'categories' => $categories->take(4) // Limit to 4 for homepage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($slug)
    {
        try {
            $category = Category::where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'category' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
 * Get categories tree for admin course creation (only subcategories with full path)
 */
public function adminTree()
{
    $subcategories = Category::where('level', 1)
        ->where('is_active', true)
        ->with('parent')
        ->orderBy('name')
        ->get()
        ->map(function ($cat) {
            return [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'full_path'  => $cat->full_path,        // e.g. "Programming > AI"
                'parent_id'  => $cat->parent_id,
                'parent_name'=> $cat->parent?->name,
                'slug'       => $cat->slug,
            ];
        });

    return response()->json([
        'success' => true,
        'message' => 'Subcategories for course creation',
        'subcategories' => $subcategories
    ]);
}
}