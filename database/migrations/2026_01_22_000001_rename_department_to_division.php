<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign keys
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        // 2. Rename table departments -> divisions
        Schema::rename('departments', 'divisions');

        // 3. Rename columns department_id -> division_id
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
        });

        // 4. Rename actor_department -> actor_division in activity logs
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->renameColumn('actor_department', 'actor_division');
        });

        // 5. Re-add foreign keys with new names
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('division_id')
                ->references('id')->on('divisions')
                ->onDelete('set null');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('division_id')
                ->references('id')->on('divisions')
                ->onDelete('cascade');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('division_id')
                ->references('id')->on('divisions')
                ->onDelete('set null');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreign('division_id')
                ->references('id')->on('divisions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // 1. Drop foreign keys
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        // 2. Rename table divisions -> departments
        Schema::rename('divisions', 'departments');

        // 3. Rename columns division_id -> department_id
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
        });

        // 4. Rename actor_division -> actor_department in activity logs
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->renameColumn('actor_division', 'actor_department');
        });

        // 5. Re-add foreign keys with original names
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('cascade');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');
        });
    }
};
