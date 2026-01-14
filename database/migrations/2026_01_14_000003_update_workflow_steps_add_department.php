<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained('departments')->onDelete('set null');
            $table->unsignedInteger('template_step_order')->nullable()->after('step_type_id');
            
            if (Schema::hasColumn('workflow_steps', 'same_department')) {
                $table->dropColumn('same_department');
            }
            if (Schema::hasColumn('workflow_steps', 'send_email')) {
                $table->dropColumn('send_email');
            }
            if (Schema::hasColumn('workflow_steps', 'signature_cell')) {
                $table->dropColumn('signature_cell');
            }
            if (Schema::hasColumn('workflow_steps', 'approved_date_cell')) {
                $table->dropColumn('approved_date_cell');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'template_step_order']);
            
            $table->boolean('same_department')->default(false)->after('step_type_id');
            $table->boolean('send_email')->default(true)->after('same_department');
            $table->string('signature_cell')->nullable()->after('send_email');
            $table->string('approved_date_cell')->nullable()->after('signature_cell');
        });
    }
};
