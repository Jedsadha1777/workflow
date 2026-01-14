<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('step_order')->constrained('roles')->onDelete('set null');
            $table->foreignId('step_type_id')->nullable()->after('role_id')->constrained('workflow_step_types')->onDelete('set null');
            
            if (Schema::hasColumn('document_approvers', 'required_role')) {
                $table->dropColumn('required_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['step_type_id']);
            $table->dropColumn(['role_id', 'step_type_id']);
            $table->string('required_role')->nullable()->after('step_order');
        });
    }
};
