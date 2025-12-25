<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->text('calculation_scripts')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->dropColumn('calculation_scripts');
        });
    }
};