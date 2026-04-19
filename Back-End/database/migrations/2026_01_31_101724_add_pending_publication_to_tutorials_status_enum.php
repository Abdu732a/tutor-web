<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tutorials MODIFY COLUMN status ENUM('draft','pending_approval','approved','pending_publication','published','archived','cancelled') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tutorials MODIFY COLUMN status ENUM('draft','pending_approval','approved','published','archived','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
