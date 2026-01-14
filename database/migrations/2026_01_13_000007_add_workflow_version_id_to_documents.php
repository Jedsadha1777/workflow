<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('workflow_version_id')
                ->nullable()
                ->after('template_document_id')
                ->constrained('workflow_versions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['workflow_version_id']);
            $table->dropColumn('workflow_version_id');
        });
    }
};
