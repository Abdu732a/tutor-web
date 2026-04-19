<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Set status based on is_published (Postgres boolean fix)
        DB::statement("
            UPDATE tutorials 
            SET 
                status = CASE 
                    WHEN is_published = true THEN 'published' 
                    ELSE 'draft' 
                END,
                created_by_role = 'tutor',
                approved_at = created_at,
                approved_by_admin_id = (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
            WHERE created_by_role IS NULL
        ");
        
        // 2. Create assignment records for existing relationships
        DB::statement("
            INSERT INTO tutorial_assignments (
                tutorial_id, 
                tutor_id, 
                assigned_by_admin_id, 
                status, 
                accepted_at, 
                created_at, 
                updated_at
            )
            SELECT 
                t.id as tutorial_id,
                t.tutor_id,
                (SELECT id FROM users WHERE role = 'admin' LIMIT 1) as assigned_by_admin_id,
                'accepted' as status,
                t.created_at as accepted_at,
                CURRENT_TIMESTAMP as created_at,
                CURRENT_TIMESTAMP as updated_at
            FROM tutorials t
            WHERE t.tutor_id IS NOT NULL 
            AND NOT EXISTS (
                SELECT 1 FROM tutorial_assignments ta 
                WHERE ta.tutorial_id = t.id AND ta.tutor_id = t.tutor_id
            )
        ");
    }

    public function down()
    {
        DB::statement("DELETE FROM tutorial_assignments");
        
        DB::statement("
            UPDATE tutorials 
            SET 
                status = 'draft',
                created_by_role = 'tutor',
                approved_by_admin_id = NULL,
                approved_at = NULL
        ");
    }
};