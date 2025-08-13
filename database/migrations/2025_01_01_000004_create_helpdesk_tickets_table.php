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
        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('helpdesk_board_id')->nullable()->constrained('helpdesk_boards')->nullOnDelete();
            $table->foreignId('helpdesk_board_slot_id')->nullable()->constrained('helpdesk_board_slots')->nullOnDelete();
            $table->foreignId('helpdesk_ticket_group_id')->nullable()->constrained('helpdesk_ticket_groups')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->integer('slot_order')->default(0);
            
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_in_charge_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority')->nullable();
            $table->string('status')->nullable();
            $table->string('story_points')->nullable();
            $table->boolean('is_frog')->default(false);
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_tickets');
    }
};
