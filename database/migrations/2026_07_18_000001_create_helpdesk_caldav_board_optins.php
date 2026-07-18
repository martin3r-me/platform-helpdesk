<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_caldav_board_optins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('helpdesk_board_id')->constrained('helpdesk_boards')->cascadeOnDelete();
            $table->timestamps();

            // Ein Opt-in pro User+Board.
            $table->unique(['user_id', 'helpdesk_board_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_caldav_board_optins');
    }
};
