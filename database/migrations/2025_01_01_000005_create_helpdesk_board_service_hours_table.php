<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_board_service_hours', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('helpdesk_board_id')->constrained('helpdesk_boards')->cascadeOnDelete();
            $table->string('name'); // z.B. "Mo-Fr 9-17 Uhr", "24/7 Support"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('service_hours')->nullable(); // JSON für komplexe Zeiten
            $table->text('auto_message_inside')->nullable(); // Nachricht während Service-Zeit
            $table->text('auto_message_outside')->nullable(); // Nachricht außerhalb Service-Zeit
            $table->boolean('use_auto_messages')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_board_service_hours');
    }
};
