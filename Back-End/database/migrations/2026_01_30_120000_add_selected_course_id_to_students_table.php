<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists before adding
        if (!Schema::hasColumn('students', 'selected_course_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->unsignedBigInteger('selected_course_id')->nullable()->after('course_type');
                $table->foreign('selected_course_id')->references('id')->on('courses')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['selected_course_id']);
            $table->dropColumn('selected_course_id');
        });
    }
};