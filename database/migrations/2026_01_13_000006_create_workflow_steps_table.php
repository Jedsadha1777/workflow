<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->onDelete('cascade');
            $table->unsignedInteger('step_order');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('step_type_id')->constrained('workflow_step_types')->onDelete('cascade');
            $table->boolean('same_department')->default(false);
            $table->boolean('send_email')->default(true);
            $table->string('signature_cell')->nullable();
            $table->string('approved_date_cell')->nullable();
            $table->timestamps();

            $table->unique(['workflow_version_id', 'step_order']);
            $table->index(['workflow_version_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
