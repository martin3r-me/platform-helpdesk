<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_error_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_board_id')
                ->constrained('helpdesk_boards')
                ->cascadeOnDelete();
            $table->foreignId('helpdesk_ticket_id')
                ->nullable()
                ->constrained('helpdesk_tickets')
                ->nullOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->string('error_hash', 64);
            $table->string('exception_class')->nullable();
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->integer('http_code')->nullable();

            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->json('sample_data')->nullable();
            $table->string('status')->default('open');

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('error_hash');
            $table->index('status');
            $table->index('http_code');
            $table->index('first_seen_at');
            $table->index('last_seen_at');
            $table->index(['helpdesk_board_id', 'error_hash', 'status'], 'hd_err_occ_board_hash_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_error_occurrences');
    }
};
