<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('step_type')->nullable()->change();
        });

        Schema::table('document_approvers', function (Blueprint $table) {
            $table->string('step_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('step_type')->default('approve')->change();
        });

        Schema::table('document_approvers', function (Blueprint $table) {
            $table->string('step_type')->default('approve')->change();
        });
    }
};