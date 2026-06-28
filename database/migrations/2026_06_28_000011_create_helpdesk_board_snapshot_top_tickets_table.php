<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_board_snapshot_top_tickets')) {
            return;
        }

        Schema::create('helpdesk_board_snapshot_top_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('helpdesk_board_snapshots', 'id', 'hbstt_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()
                ->constrained('helpdesk_tickets', 'id', 'hbstt_ticket_fk')
                ->nullOnDelete();

            // Denorm — bleibt erhalten auch wenn Ticket spaeter geloescht
            $table->uuid('ticket_uuid')->nullable();
            $table->string('ticket_title', 500);
            $table->dateTime('due_date')->nullable();
            $table->dateTime('ticket_created_at')->nullable(); // fuer Alter-Berechnung
            $table->boolean('is_overdue')->default(false);
            $table->unsignedInteger('postpone_count')->default(0);
            $table->string('priority', 16)->nullable();
            $table->string('escalation_level', 16)->nullable();
            $table->unsignedInteger('escalation_count')->default(0);
            $table->string('story_points', 8)->nullable();

            // User in charge — denorm
            $table->foreignId('user_in_charge_id')->nullable()
                ->constrained('users', 'id', 'hbstt_user_fk')
                ->nullOnDelete();
            $table->string('user_in_charge_name', 255)->nullable();

            // Rang innerhalb dieses Snapshots (1..N)
            $table->unsignedTinyInteger('rank');

            $table->timestamps();

            $table->index(['snapshot_id', 'rank'], 'hbstt_snap_rank_idx');
            $table->index(['ticket_id', 'snapshot_id'], 'hbstt_ticket_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_snapshot_top_tickets');
    }
};
