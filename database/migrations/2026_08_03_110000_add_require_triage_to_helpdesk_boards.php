<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Triage-Pflicht als Eigenschaft der QUELLE (Board), einheitlich mit dev/planner — nur der
 * Default kippt: Helpdesk-Tickets kommen ROH von außen (E-Mail), daher Default TRUE. So macht
 * die Triage (Eingangsbestätigung + Einordnung) den Ticket-Zustand, bevor der Supporter ran
 * darf — exakt das bisherige Verhalten, jetzt aber als expliziter, pro Board abschaltbarer Flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_boards', 'require_triage')) {
                $table->boolean('require_triage')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            $table->dropColumn('require_triage');
        });
    }
};
