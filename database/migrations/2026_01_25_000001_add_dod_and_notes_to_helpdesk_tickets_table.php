<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            // Beschreibung wird zu "Anmerkung" (notes)
            // Bestehende description-Spalte wird zu notes umbenannt
            $table->renameColumn('description', 'notes');
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            // DoD (Definition of Done) als JSON-Array
            // Format: [{"text": "...", "checked": true/false}, ...]
            $table->json('dod')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->renameColumn('notes', 'description');
            $table->dropColumn('dod');
        });
    }
};
