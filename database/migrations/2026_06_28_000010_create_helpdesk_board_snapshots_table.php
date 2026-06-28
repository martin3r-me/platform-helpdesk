<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Health\Support\HealthSnapshotSchema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_board_snapshots')) {
            return;
        }

        Schema::create('helpdesk_board_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('helpdesk_board_id')
                ->constrained('helpdesk_boards', 'id', 'hbs_board_fk')
                ->cascadeOnDelete();

            // Standard-Snapshot-Spalten aus core
            HealthSnapshotSchema::columns($table);

            // ── Ticket-Counts ──
            $table->unsignedInteger('tickets_total')->default(0);
            $table->unsignedInteger('tickets_open')->default(0);
            $table->unsignedInteger('tickets_done')->default(0);
            $table->unsignedInteger('tickets_overdue')->default(0);
            $table->unsignedInteger('tickets_with_due_date')->default(0);

            // ── Escalation ──
            $table->unsignedInteger('tickets_escalated')->default(0);   // level != NONE
            $table->unsignedInteger('tickets_critical')->default(0);    // level in [CRITICAL, URGENT]
            $table->unsignedInteger('escalations_total_lifetime')->default(0); // sum(escalation_count) ueber alle Tickets

            // ── Story Points ──
            $table->unsignedInteger('story_points_total')->default(0);
            $table->unsignedInteger('story_points_open')->default(0);
            $table->unsignedInteger('story_points_done')->default(0);

            // ── SLA ──
            $table->boolean('has_sla')->default(false);
            $table->unsignedInteger('sla_response_hours')->nullable();   // frozen vom Board zum Snapshot-Zeitpunkt
            $table->unsignedInteger('sla_resolution_hours')->nullable();
            $table->unsignedInteger('tickets_breaching_resolution')->default(0); // open + age > resolution_hours

            // ── Workload ──
            $table->unsignedInteger('active_users_count')->default(0);    // User mit >=1 open ticket
            $table->unsignedInteger('unassigned_tickets')->default(0);

            $table->timestamps();

            // Unique: max 1 Snapshot pro Board pro Tag
            $table->unique(['helpdesk_board_id', 'taken_on'], 'hbs_board_day_uniq');

            // Indizes fuer Trend-Queries
            $table->index(['helpdesk_board_id', 'taken_at'], 'hbs_board_taken_idx');
            $table->index(['team_id', 'taken_at'], 'hbs_team_taken_idx');
            $table->index('taken_at', 'hbs_taken_idx');
        });

        // Self-FK fuer prev_snapshot_id separat
        Schema::table('helpdesk_board_snapshots', function (Blueprint $table) {
            $table->foreign('prev_snapshot_id', 'hbs_prev_fk')
                ->references('id')->on('helpdesk_board_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_board_snapshots', function (Blueprint $table) {
            $table->dropForeign('hbs_prev_fk');
        });
        Schema::dropIfExists('helpdesk_board_snapshots');
    }
};
