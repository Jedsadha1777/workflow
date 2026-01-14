<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_step_types', function (Blueprint $table) {
            $table->boolean('send_email')->default(true)->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_step_types', function (Blueprint $table) {
            $table->dropColumn('send_email');
        });
    }
};
