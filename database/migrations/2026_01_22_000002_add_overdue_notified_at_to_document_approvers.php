<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_approvers', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_at');
        });
    }
};
