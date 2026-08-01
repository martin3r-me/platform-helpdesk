<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Supporter-Blocker aus dem Live-Smoke-Test:
 *  1) Schema-Drift: der deployten helpdesk_tickets fehlen die Eskalations-Spalten
 *     (Create-Migration wurde nachträglich erweitert, lief aber nicht nach) → escalate = 500.
 *     Guarded nachziehen (hasColumn), damit fresh-installs nicht kollidieren.
 *  2) agent_handled_at: propose/escalate ließen das Ticket im Claim-Pool → der Worker
 *     zog es endlos erneut. Marker „an Mensch übergeben" → next-triaged überspringt es
 *     (analog agent_waiting_at für die Kunden-Rückfrage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_tickets', 'escalation_level')) {
                $table->string('escalation_level', 16)->default('none')->after('priority');
            }
            if (! Schema::hasColumn('helpdesk_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('escalation_level');
            }
            if (! Schema::hasColumn('helpdesk_tickets', 'escalation_count')) {
                $table->integer('escalation_count')->default(0)->after('escalated_at');
            }
            if (! Schema::hasColumn('helpdesk_tickets', 'agent_handled_at')) {
                $table->timestamp('agent_handled_at')->nullable()->after('agent_session_id');
                $table->index('agent_handled_at', 'helpdesk_tickets_agent_handled_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'agent_handled_at')) {
                $table->dropIndex('helpdesk_tickets_agent_handled_idx');
                $table->dropColumn('agent_handled_at');
            }
        });
    }
};
