<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warten-auf-Kundenantwort für die Supporter-Rückfrage (mehrstufiger Dialog statt One-Shot):
 *  - agent_waiting_at: gesetzt = Ticket wartet auf eine Antwort des Kunden im E-Mail-Thread.
 *    Der normale Claim (next-triaged) überspringt es; erst wenn eine neue Inbound-Mail
 *    eintrifft (last_inbound_at > agent_waiting_at), holt der Resume-Pass es zurück.
 *  - agent_session_id: die Claude-Session, die beim Weiter-Claimen per --resume fortgesetzt
 *    wird → kein Neuaufsetzen, kein Reassign an einen Menschen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->timestamp('agent_waiting_at')->nullable()->after('locked_by_user_id');
            $table->string('agent_session_id', 255)->nullable()->after('agent_waiting_at');
            $table->index('agent_waiting_at', 'helpdesk_tickets_agent_waiting_idx');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropIndex('helpdesk_tickets_agent_waiting_idx');
            $table->dropColumn(['agent_waiting_at', 'agent_session_id']);
        });
    }
};
