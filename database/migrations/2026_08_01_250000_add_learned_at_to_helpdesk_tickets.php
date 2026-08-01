<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learn-Stufe (nächtlich): der Worker destilliert aus abgeschlossenen Tickets OHNE
 * `resolution` einen wiederverwendbaren Lösungsweg (oder „nichts zu lernen"). `learned_at`
 * markiert verarbeitete Tickets → der nächtliche Sweep zieht jedes nur EINMAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->timestamp('learned_at')->nullable()->after('resolution');
            $table->index(['is_done', 'learned_at'], 'helpdesk_tickets_learnable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropIndex('helpdesk_tickets_learnable_idx');
            $table->dropColumn('learned_at');
        });
    }
};
