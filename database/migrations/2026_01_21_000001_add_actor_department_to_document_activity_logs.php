<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->string('actor_department', 255)->nullable()->after('actor_role');
        });
    }

    public function down(): void
    {
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->dropColumn('actor_department');
        });
    }
};