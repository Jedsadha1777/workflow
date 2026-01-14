<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('template_workflows');
    }

    public function down(): void
    {
        Schema::create('template_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_document_id')->constrained('template_documents')->onDelete('cascade');
            $table->integer('step_order');
            $table->string('required_role');
            $table->boolean('same_department')->default(false);
            $table->string('signature_cell')->nullable();
            $table->string('approved_date_cell')->nullable();
            $table->timestamps();
            
            $table->index(['template_document_id', 'step_order']);
        });
    }
};
