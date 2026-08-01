<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freigabe-Loop (Vorschlag-Modus): der Worker erarbeitet einen Lösungs-Entwurf, legt ihn
 * in den Kontext-Thread und WARTET auf die Freigabe des Supervisors im Thread (statt sofort
 * zu senden). Dafür muss der Warten-Zustand zwei Sorten unterscheiden:
 *  - 'customer' : wartet auf eine Kundenantwort per E-Mail (Resume über last_inbound_at).
 *  - 'approval' : wartet auf ein OK des Supervisors im Kontext-Thread (Resume über TerminalMessage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->string('agent_waiting_kind', 16)->nullable()->after('agent_waiting_at');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropColumn('agent_waiting_kind');
        });
    }
};
