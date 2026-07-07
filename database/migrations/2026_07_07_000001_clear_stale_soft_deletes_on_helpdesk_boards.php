<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bereinigt stale deleted_at-Werte auf helpdesk_boards.
 *
 * Hintergrund: Die Tabelle wurde mit $table->softDeletes() angelegt, das Modell
 * HelpdeskBoard nutzte aber lange KEIN SoftDeletes-Trait. Dadurch hat die App
 * deleted_at komplett ignoriert — Boards mit gesetztem deleted_at waren trotzdem
 * ueberall live (gelistet, gesnapshottet). Jetzt wird SoftDeletes am Modell
 * aktiviert; ohne diese Bereinigung wuerden genau diese aktiven Boards
 * schlagartig aus der UI verschwinden.
 *
 * Da die App diese Boards bereits als aktiv behandelt, ist der aktuelle
 * Sichtbarkeits-Stand die Wahrheit: alle deleted_at-Werte werden auf NULL
 * gesetzt. (Nicht umkehrbar — welche Boards zuvor markiert waren, laesst sich
 * nachtraeglich nicht rekonstruieren.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_boards') || ! Schema::hasColumn('helpdesk_boards', 'deleted_at')) {
            return;
        }

        // DB::table umgeht den (jetzt aktiven) SoftDeletes-Scope bewusst.
        $affected = DB::table('helpdesk_boards')
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        if ($affected > 0) {
            Log::info('[migration] Stale deleted_at auf helpdesk_boards bereinigt', [
                'boards_reactivated' => $affected,
            ]);
        }
    }

    public function down(): void
    {
        // Nicht umkehrbar: die urspruenglichen deleted_at-Werte sind nicht rekonstruierbar.
    }
};
