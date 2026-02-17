<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('helpdesk_board_id')->constrained('helpdesk_boards')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('title');
            $table->text('problem');
            $table->text('solution');
            $table->json('tags')->nullable();
            $table->foreignId('source_ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'helpdesk_board_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_knowledge_entries');
    }
};
