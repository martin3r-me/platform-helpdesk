<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_board_error_settings')) {
            return;
        }

        Schema::create('helpdesk_board_error_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_board_id')
                ->unique()
                ->constrained('helpdesk_boards')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->boolean('enabled')->default(false);
            $table->json('capture_codes')->nullable();
            $table->json('priority_mapping')->nullable();
            $table->integer('dedupe_window_hours')->default(24);
            $table->boolean('auto_create_ticket')->default(true);
            $table->boolean('include_stack_trace')->default(true);
            $table->integer('stack_trace_limit')->default(50);

            $table->timestamps();

            $table->index('team_id');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_error_settings');
    }
};
