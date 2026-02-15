<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_board_slas', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('order')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_board_slas', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropColumn('team_id');
        });
    }
};


