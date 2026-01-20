<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('step_type')->default('pending')->after('template_step_order');
        });

        Schema::table('document_approvers', function (Blueprint $table) {
            $table->string('step_type')->default('pending')->after('approved_date_cell');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('step_type');
        });

        Schema::table('document_approvers', function (Blueprint $table) {
            $table->dropColumn('step_type');
        });
    }
};