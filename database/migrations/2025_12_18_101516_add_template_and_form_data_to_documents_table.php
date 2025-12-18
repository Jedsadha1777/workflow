<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('template_document_id')->nullable()->after('department_id')->constrained('template_documents')->onDelete('set null');
            $table->json('form_data')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['template_document_id']);
            $table->dropColumn(['template_document_id', 'form_data']);
        });
    } 
};