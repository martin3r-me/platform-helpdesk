<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_board_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_board_id')->unique()->constrained('helpdesk_boards')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            
            // Allgemeine Einstellungen
            $table->boolean('auto_response_enabled')->default(false);
            $table->integer('auto_response_timing_minutes')->default(30);
            $table->boolean('auto_response_immediate_enabled')->default(true);
            $table->decimal('auto_response_confidence_threshold', 3, 2)->default(0.90);
            
            // Auto-Assignment
            $table->boolean('auto_assignment_enabled')->default(false);
            $table->decimal('auto_assignment_confidence_threshold', 3, 2)->default(0.70);
            
            // AI-Konfiguration
            $table->string('ai_model', 50)->default('gpt-4o-mini');
            $table->boolean('human_in_loop_enabled')->default(true);
            $table->decimal('human_in_loop_threshold', 3, 2)->default(0.90);
            $table->boolean('ai_enabled_for_escalated')->default(false);
            
            // Knowledge Base
            $table->json('knowledge_base_categories')->nullable();
            
            // Template-System
            $table->unsignedBigInteger('template_id')->nullable();
            
            $table->timestamps();
            
            $table->index('team_id');
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_ai_settings');
    }
};

