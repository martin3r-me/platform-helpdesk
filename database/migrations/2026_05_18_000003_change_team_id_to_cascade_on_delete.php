<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aendert alle team_id FKs im Helpdesk-Modul von nullOnDelete
 * auf cascadeOnDelete, damit Team-Loeschungen sauber durchkaskadieren.
 */
return new class extends Migration
{
    private array $tables = [
        'helpdesk_boards',
        'helpdesk_ticket_groups',
        'helpdesk_tickets',
        'helpdesk_board_ai_settings',
        'helpdesk_knowledge_base',
        'helpdesk_board_error_settings',
        'helpdesk_error_occurrences',
        'helpdesk_knowledge_entries',
        'helpdesk_board_slas',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign([$this->fkName($table)]);
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreign('team_id', $this->fkName($table))
                  ->references('id')
                  ->on('teams')
                  ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign([$this->fkName($table)]);
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreign('team_id', $this->fkName($table))
                  ->references('id')
                  ->on('teams')
                  ->nullOnDelete();
            });
        }
    }

    private function fkName(string $table): string
    {
        return $table . '_team_id_foreign';
    }
};
