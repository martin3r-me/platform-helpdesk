<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_ticket_escalations', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('helpdesk_ticket_id')
                  ->constrained('helpdesk_tickets')
                  ->cascadeOnDelete();
            
            $table->enum('escalation_level', ['warning', 'escalated', 'critical', 'urgent']);
            $table->text('reason')->nullable(); // Grund für Eskalation
            
            $table->foreignId('escalated_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            
            $table->foreignId('resolved_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            
            $table->timestamp('escalated_at');
            $table->timestamp('resolved_at')->nullable();
            
            $table->json('notification_sent')->nullable(); // Welche Benachrichtigungen gesendet wurden
            $table->text('notes')->nullable(); // Zusätzliche Notizen
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ticket_escalations');
    }
};
