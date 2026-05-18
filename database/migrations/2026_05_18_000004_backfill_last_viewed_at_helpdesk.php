<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Setzt last_viewed_at fuer alle bestehenden Records auf now(),
 * damit die Staleness-Uhr ab heute fuer alle tickt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('helpdesk_boards')
            ->whereNull('last_viewed_at')
            ->update(['last_viewed_at' => $now]);

        DB::table('helpdesk_tickets')
            ->whereNull('last_viewed_at')
            ->update(['last_viewed_at' => $now]);
    }

    public function down(): void
    {
        DB::table('helpdesk_boards')->update(['last_viewed_at' => null]);
        DB::table('helpdesk_tickets')->update(['last_viewed_at' => null]);
    }
};
