<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop step_type_id FK from workflow_steps
        if (Schema::hasColumn('workflow_steps', 'step_type_id')) {
            $fkExists = DB::select("
                SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'workflow_steps' 
                AND CONSTRAINT_NAME = 'workflow_steps_step_type_id_foreign'
            ");
            
            if (!empty($fkExists)) {
                Schema::table('workflow_steps', function (Blueprint $table) {
                    $table->dropForeign(['step_type_id']);
                });
            }
            
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->dropColumn('step_type_id');
            });
        }

        // 2. Drop step_type_id FK from document_approvers
        if (Schema::hasColumn('document_approvers', 'step_type_id')) {
            $fkExists2 = DB::select("
                SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'document_approvers' 
                AND CONSTRAINT_NAME = 'document_approvers_step_type_id_foreign'
            ");
            
            if (!empty($fkExists2)) {
                Schema::table('document_approvers', function (Blueprint $table) {
                    $table->dropForeign(['step_type_id']);
                });
            }
            
            Schema::table('document_approvers', function (Blueprint $table) {
                $table->dropColumn('step_type_id');
            });
        }

        // 3. Drop workflow_step_types table
        Schema::dropIfExists('workflow_step_types');
    }

    public function down(): void
    {
        Schema::create('workflow_step_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('send_email')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('step_type_id')->nullable()->constrained('workflow_step_types')->onDelete('set null');
        });

        Schema::table('document_approvers', function (Blueprint $table) {
            $table->foreignId('step_type_id')->nullable()->constrained('workflow_step_types')->onDelete('set null');
        });
    }
};
