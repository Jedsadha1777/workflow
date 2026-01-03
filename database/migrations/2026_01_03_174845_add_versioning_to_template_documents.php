<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('name');
            $table->string('status')->default('DRAFT')->after('is_active');
            $table->foreignId('parent_id')->nullable()->after('status')
                ->constrained('template_documents')->onDelete('set null');
            
            $table->index(['name', 'version']);
            $table->index(['status']);
        });
        
        DB::statement("UPDATE template_documents SET status = 'PUBLISHED' WHERE is_active = 1");
        DB::statement("UPDATE template_documents SET status = 'DRAFT' WHERE is_active = 0");
    }

    public function down(): void
    {
        Schema::table('template_documents', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['name', 'version']);
            $table->dropIndex(['status']);
            $table->dropColumn(['version', 'status', 'parent_id']);
        });
    }
};
