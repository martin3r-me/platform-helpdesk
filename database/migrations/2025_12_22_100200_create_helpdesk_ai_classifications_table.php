<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_ai_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->unique()->constrained('helpdesk_tickets')->cascadeOnDelete();
            
            $table->string('category')->nullable();
            $table->string('priority_prediction')->nullable();
            $table->foreignId('assignee_suggestion_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->decimal('confidence_score', 3, 2)->default(0.00);
            $table->string('ai_model_used', 50)->nullable();
            $table->json('raw_response')->nullable();
            
            $table->timestamps();
            
            $table->index('category');
            $table->index('priority_prediction');
            $table->index('assignee_suggestion_user_id');
            $table->index('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ai_classifications');
    }
};

