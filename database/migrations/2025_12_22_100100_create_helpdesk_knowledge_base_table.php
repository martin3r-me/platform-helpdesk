<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('helpdesk_board_id')->nullable()->constrained('helpdesk_boards')->nullOnDelete();
            
            $table->string('title');
            $table->text('content')->nullable(); // Wird verschlüsselt
            $table->string('content_hash', 64)->nullable();
            
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            
            $table->decimal('success_rate', 3, 2)->default(0.00);
            $table->integer('usage_count')->default(0);
            $table->string('language', 10)->default('de');
            
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index('team_id');
            $table->index('helpdesk_board_id');
            $table->index('category');
            $table->index('content_hash');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_knowledge_base');
    }
};

