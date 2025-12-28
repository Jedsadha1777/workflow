<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->json('pdf_layout_html')->nullable()->after('content');
            $table->string('pdf_orientation', 20)->default('portrait')->after('pdf_layout_html');
        });
    }

    public function down(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->dropColumn(['pdf_layout_html', 'pdf_orientation']);
        });
    }
};
