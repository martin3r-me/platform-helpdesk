<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reihenfolge beachten wegen Foreign Keys
        Schema::dropIfExists('helpdesk_ai_responses');
        Schema::dropIfExists('helpdesk_ticket_resolutions');
        Schema::dropIfExists('helpdesk_ai_classifications');
        Schema::dropIfExists('helpdesk_knowledge_base');
        Schema::dropIfExists('helpdesk_board_ai_settings');
    }

    public function down(): void
    {
        // Tabellen werden nicht wiederhergestellt - AI wird neu aufgebaut
    }
};
