<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Board-seitige Kategorien (kuratierbar) + Ticket-Zuordnung. Jedes Board definiert
 * seine eigenen Kategorien; die Triage ordnet neue (und nachträglich alte) Tickets ein.
 * `description` + `examples` sind die semantischen Anker (kein Stichwort-Matching);
 * `examples` wächst durch Kuration/Korrekturen und speist später den Retrieval-Index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_board_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('helpdesk_board_id')->constrained('helpdesk_boards')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();   // was gehört rein/raus (Unterscheidungsmerkmal)
            $table->json('examples')->nullable();       // Few-Shot-Anker: Beispiel-Formulierungen
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['helpdesk_board_id', 'is_active']);
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreignId('helpdesk_board_category_id')->nullable()->after('helpdesk_board_slot_id')
                ->constrained('helpdesk_board_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('helpdesk_board_category_id');
        });
        Schema::dropIfExists('helpdesk_board_categories');
    }
};
