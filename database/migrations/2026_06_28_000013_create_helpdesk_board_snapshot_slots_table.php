<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_board_snapshot_slots')) {
            return;
        }

        Schema::create('helpdesk_board_snapshot_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('helpdesk_board_snapshots', 'id', 'hbssl_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()
                ->constrained('helpdesk_board_slots', 'id', 'hbssl_slot_fk')
                ->nullOnDelete();

            $table->string('slot_name', 255);
            $table->integer('slot_order')->default(0);

            $table->unsignedInteger('open_tickets')->default(0);
            $table->unsignedInteger('done_tickets')->default(0);
            $table->unsignedInteger('total_tickets')->default(0);

            $table->timestamps();

            $table->index(['slot_id', 'snapshot_id'], 'hbssl_slot_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_snapshot_slots');
    }
};
