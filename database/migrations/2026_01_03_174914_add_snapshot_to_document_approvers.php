<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            if (!Schema::hasColumn('document_approvers', 'required_role')) {
                $table->string('required_role')->nullable()->after('step_order');
            }
            
            if (!Schema::hasColumn('document_approvers', 'same_department')) {
                $table->boolean('same_department')->nullable()->after('required_role');
            }
            
            if (!Schema::hasColumn('document_approvers', 'approver_name')) {
                $table->string('approver_name')->nullable()->after('approver_id');
            }
            
            if (!Schema::hasColumn('document_approvers', 'approver_email')) {
                $table->string('approver_email')->nullable()->after('approver_name');
            }
        });

        // Drop FK เดิม
        try {
            Schema::table('document_approvers', function (Blueprint $table) {
                $table->dropForeign(['approver_id']);
            });
        } catch (\Exception $e) {
            // FK ไม่มี - ไม่เป็นไร
        }
        
        // เปลี่ยน approver_id เป็น nullable
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_id')->nullable()->change();
        });
        
        // เพิ่ม FK ใหม่
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->foreign('approver_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop FK ใหม่
        try {
            Schema::table('document_approvers', function (Blueprint $table) {
                $table->dropForeign(['approver_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }
        
        // เปลี่ยน approver_id กลับเป็น NOT NULL
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_id')->nullable(false)->change();
        });
        
        // เพิ่ม FK เดิมกลับ
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->foreign('approver_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
        
        // ลบ columns
        Schema::table('document_approvers', function (Blueprint $table) {
            $columns = ['required_role', 'same_department', 'approver_name', 'approver_email'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('document_approvers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};