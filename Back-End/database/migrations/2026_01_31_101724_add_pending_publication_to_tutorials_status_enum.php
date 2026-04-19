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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tutorials DROP CONSTRAINT IF EXISTS tutorials_status_check');
            DB::statement("ALTER TABLE tutorials ADD CONSTRAINT tutorials_status_check CHECK (status::text IN ('draft', 'pending_approval', 'approved', 'pending_publication', 'published', 'archived', 'cancelled'))");
        } else {
            DB::statement("ALTER TABLE tutorials MODIFY COLUMN status ENUM('draft','pending_approval','approved','pending_publication','published','archived','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tutorials DROP CONSTRAINT IF EXISTS tutorials_status_check');
            DB::statement("ALTER TABLE tutorials ADD CONSTRAINT tutorials_status_check CHECK (status::text IN ('draft', 'pending_approval', 'approved', 'published', 'archived', 'cancelled'))");
        } else {
            DB::statement("ALTER TABLE tutorials MODIFY COLUMN status ENUM('draft','pending_approval','approved','published','archived','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }
};
