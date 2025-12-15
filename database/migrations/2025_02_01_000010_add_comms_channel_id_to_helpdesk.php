<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            $table->string('comms_channel_id')->nullable()->after('helpdesk_board_sla_id');
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->string('comms_channel_id')->nullable()->after('helpdesk_ticket_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            $table->dropColumn('comms_channel_id');
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropColumn('comms_channel_id');
        });
    }
};

