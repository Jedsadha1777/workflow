<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->foreignId('template_id')->constrained('template_documents')->onDelete('cascade');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('DRAFT');
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
            $table->index(['workflow_id', 'status']);
            $table->index(['template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
