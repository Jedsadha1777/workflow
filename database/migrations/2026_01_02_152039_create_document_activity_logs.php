<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_activity_logs', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('document_id');
            $table->string('document_title', 500)->nullable();
            
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255);
            $table->string('actor_email', 255)->nullable();
            $table->string('actor_role', 50);
            
            // Action - เก็บเป็น VARCHAR
            $table->string('action', 50);
            
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            
            // Approval step info
            $table->integer('step_order')->nullable();
            
            // Comment/Reason
            $table->text('comment')->nullable();
            
            // Metadata - เก็บข้อมูลเพิ่มเติม
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamp('performed_at');
            
            // Indexes
            $table->index(['document_id', 'performed_at']);
            $table->index(['actor_id', 'action']);
            $table->index('performed_at');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activity_logs');
    }
};