<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prozess-bewusste Lösung eines gelösten Tickets — vom Worker beim Schließen aus dem
 * vollen Verlauf destilliert (Problem → Weg → Fix), nicht der rohe Mail-Thread. Wird
 * beim Auto-Indexing als `solution`-Metadatum in den board-scoped Vektor-Index gelegt,
 * damit das Retrieval nicht nur „ähnliches Problem", sondern auch „so wurde es gelöst" liefert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->text('resolution')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropColumn('resolution');
        });
    }
};
