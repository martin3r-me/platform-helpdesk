<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_board_slas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name'); // z.B. "Standard", "Kritisch", "Express"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('response_time_hours')->nullable(); // Reaktionszeit in Stunden
            $table->integer('resolution_time_hours')->nullable(); // Lösungszeit in Stunden
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_slas');
    }
};
