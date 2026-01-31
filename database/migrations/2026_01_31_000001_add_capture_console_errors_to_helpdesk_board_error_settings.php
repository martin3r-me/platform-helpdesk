<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_board_error_settings', function (Blueprint $table) {
            $table->boolean('capture_console_errors')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_board_error_settings', function (Blueprint $table) {
            $table->dropColumn('capture_console_errors');
        });
    }
};
