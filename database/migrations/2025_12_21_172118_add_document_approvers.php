<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->string('approved_date_cell')->nullable()->after('signature_cell');
        });
    }

    public function down(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->dropColumn('approved_date_cell');
        });
    }
};