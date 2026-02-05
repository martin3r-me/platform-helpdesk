<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_boards', 'comms_channel_id')) {
                $table->dropColumn('comms_channel_id');
            }
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'comms_channel_id')) {
                $table->dropColumn('comms_channel_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_boards', function (Blueprint $table) {
            $table->string('comms_channel_id')->nullable();
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->string('comms_channel_id')->nullable();
        });
    }
};
