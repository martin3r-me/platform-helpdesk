<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_ai_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->string('comms_channel_id')->nullable();
            
            $table->enum('response_type', ['immediate', 'delayed', 'manual'])->default('delayed');
            $table->text('response_text')->nullable(); // Wird verschlüsselt
            $table->string('response_text_hash', 64)->nullable();
            
            $table->decimal('confidence_score', 3, 2)->default(0.00);
            $table->timestamp('sent_at')->nullable();
            $table->enum('user_feedback', ['positive', 'negative'])->nullable();
            
            $table->string('ai_model_used', 50)->nullable();
            $table->foreignId('knowledge_base_entry_id')->nullable()->constrained('helpdesk_knowledge_base')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index('helpdesk_ticket_id');
            $table->index('comms_channel_id');
            $table->index('response_type');
            $table->index('response_text_hash');
            $table->index('sent_at');
            $table->index('knowledge_base_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ai_responses');
    }
};

