<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old unique index on workflow_steps
        $indexExists = DB::select("
            SHOW INDEX FROM workflow_steps 
            WHERE Key_name = 'workflow_steps_workflow_version_id_step_order_unique'
        ");

        if (!empty($indexExists)) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->dropUnique('workflow_steps_workflow_version_id_step_order_unique');
            });
        }
    }

    public function down(): void
    {
        // ไม่สร้างกลับเพราะ workflow_version_id ถูกลบแล้ว
    }
};
