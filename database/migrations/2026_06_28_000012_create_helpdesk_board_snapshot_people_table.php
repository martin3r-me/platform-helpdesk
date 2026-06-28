<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_board_snapshot_people')) {
            return;
        }

        Schema::create('helpdesk_board_snapshot_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('helpdesk_board_snapshots', 'id', 'hbspp_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users', 'id', 'hbspp_user_fk')
                ->nullOnDelete();

            $table->string('user_name', 255);

            $table->unsignedInteger('open_tickets')->default(0);
            $table->unsignedInteger('done_tickets')->default(0);
            $table->unsignedInteger('overdue_tickets')->default(0);
            $table->unsignedInteger('escalated_tickets')->default(0);
            $table->unsignedInteger('sp_open')->default(0);
            $table->unsignedInteger('sp_done')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'snapshot_id'], 'hbspp_user_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_snapshot_people');
    }
};
