<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_ticket_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->unique()->constrained('helpdesk_tickets')->cascadeOnDelete();
            
            $table->text('resolution_text')->nullable(); // Wird verschlüsselt
            $table->string('resolution_text_hash', 64)->nullable();
            
            $table->boolean('ai_generated')->default(false);
            $table->boolean('user_confirmed')->default(false);
            $table->decimal('effectiveness_score', 3, 2)->nullable();
            
            $table->foreignId('knowledge_base_entry_id')->nullable()->constrained('helpdesk_knowledge_base')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index('helpdesk_ticket_id');
            $table->index('resolution_text_hash');
            $table->index('ai_generated');
            $table->index('knowledge_base_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ticket_resolutions');
    }
};

