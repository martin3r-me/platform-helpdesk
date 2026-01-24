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
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('escalation_count');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by_user_id')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            
            $table->index(['is_locked'], 'helpdesk_tickets_is_locked_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropIndex('helpdesk_tickets_is_locked_idx');
            $table->dropForeign(['locked_by_user_id']);
            $table->dropColumn(['is_locked', 'locked_at', 'locked_by_user_id']);
        });
    }
};
