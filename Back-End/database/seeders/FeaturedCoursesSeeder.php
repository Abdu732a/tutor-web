<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Category;

class FeaturedCoursesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create some categories if they don't exist
        $categories = [
            ['name' => 'Programming', 'slug' => 'programming', 'description' => 'Learn programming languages and frameworks', 'is_active' => true],
            ['name' => 'School Grades', 'slug' => 'school-grades', 'description' => 'Academic subjects for all grades', 'is_active' => true],
            ['name' => 'Languages', 'slug' => 'languages', 'description' => 'Learn new languages', 'is_active' => true],
            ['name' => 'Entrance Exams', 'slug' => 'entrance-exams', 'description' => 'Exam preparation courses', 'is_active' => true],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }

        // Get category IDs
        $programmingCategory = Category::where('slug', 'programming')->first();
        $schoolGradesCategory = Category::where('slug', 'school-grades')->first();
        $languagesCategory = Category::where('slug', 'languages')->first();
        $entranceExamsCategory = Category::where('slug', 'entrance-exams')->first();

        // Create featured courses
        $featuredCourses = [
            [
                'title' => 'Complete Web Development Bootcamp',
                'description' => 'Learn HTML, CSS, JavaScript, React, Node.js and more in this comprehensive full-stack development course.',
                'category_id' => $programmingCategory?->id,
                'duration_hours' => 40,
                'price_group' => 1500.00,
                'price_individual' => 2000.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['HTML & CSS Fundamentals', 'JavaScript ES6+', 'React.js', 'Node.js & Express', 'Database Integration'],
                'learning_outcomes' => ['Build responsive websites', 'Create dynamic web applications', 'Work with databases', 'Deploy applications']
            ],
            [
                'title' => 'Mathematics Grade 10',
                'description' => 'Complete mathematics curriculum for grade 10 students covering algebra, geometry, and trigonometry.',
                'category_id' => $schoolGradesCategory?->id,
                'duration_hours' => 30,
                'price_group' => 800.00,
                'price_individual' => 1200.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['Algebra', 'Geometry', 'Trigonometry', 'Statistics', 'Problem Solving'],
                'learning_outcomes' => ['Master algebraic concepts', 'Understand geometric principles', 'Apply trigonometric functions', 'Solve complex problems']
            ],
            [
                'title' => 'English Language Mastery',
                'description' => 'Comprehensive English course to improve speaking, writing, reading, and listening skills.',
                'category_id' => $languagesCategory?->id,
                'duration_hours' => 25,
                'price_group' => 600.00,
                'price_individual' => 900.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['Grammar Fundamentals', 'Vocabulary Building', 'Speaking Practice', 'Writing Skills', 'Reading Comprehension'],
                'learning_outcomes' => ['Speak fluently', 'Write effectively', 'Understand complex texts', 'Communicate confidently']
            ],
            [
                'title' => 'University Entrance Exam Preparation',
                'description' => 'Comprehensive preparation for university entrance examinations with practice tests and strategies.',
                'category_id' => $entranceExamsCategory?->id,
                'duration_hours' => 35,
                'price_group' => 1200.00,
                'price_individual' => 1800.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['Mathematics Review', 'Science Concepts', 'English Proficiency', 'Critical Thinking', 'Test Strategies'],
                'learning_outcomes' => ['Excel in entrance exams', 'Master test-taking strategies', 'Build confidence', 'Achieve target scores']
            ],
            [
                'title' => 'Data Science with Python',
                'description' => 'Learn data analysis, machine learning, and data visualization using Python and popular libraries.',
                'category_id' => $programmingCategory?->id,
                'duration_hours' => 45,
                'price_group' => 1800.00,
                'price_individual' => 2500.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['Python Fundamentals', 'Data Analysis with Pandas', 'Machine Learning', 'Data Visualization', 'Real Projects'],
                'learning_outcomes' => ['Analyze complex datasets', 'Build ML models', 'Create visualizations', 'Work on real projects']
            ],
            [
                'title' => 'Biology Grade 11',
                'description' => 'Advanced biology concepts for grade 11 students including cell biology, genetics, and ecology.',
                'category_id' => $schoolGradesCategory?->id,
                'duration_hours' => 28,
                'price_group' => 900.00,
                'price_individual' => 1300.00,
                'is_active' => true,
                'is_featured' => true,
                'curriculum' => ['Cell Biology', 'Genetics', 'Evolution', 'Ecology', 'Human Biology'],
                'learning_outcomes' => ['Understand cellular processes', 'Master genetic principles', 'Explore evolutionary concepts', 'Study ecosystems']
            ]
        ];

        foreach ($featuredCourses as $courseData) {
            Course::firstOrCreate(
                ['title' => $courseData['title']],
                $courseData
            );
        }

        $this->command->info('Featured courses seeded successfully!');
    }
}