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
            // Prüfe ob Spalten bereits existieren, bevor wir sie hinzufügen
            if (!Schema::hasColumn('helpdesk_tickets', 'is_locked')) {
                $table->boolean('is_locked')->default(false);
            }
            
            if (!Schema::hasColumn('helpdesk_tickets', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            
            if (!Schema::hasColumn('helpdesk_tickets', 'locked_by_user_id')) {
                $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
        
        // Index separat hinzufügen (nur wenn Spalte existiert)
        if (Schema::hasColumn('helpdesk_tickets', 'is_locked')) {
            Schema::table('helpdesk_tickets', function (Blueprint $table) {
                // Prüfe ob Index bereits existiert
                try {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    $indexesFound = $sm->listTableIndexes('helpdesk_tickets');
                    if (!isset($indexesFound['helpdesk_tickets_is_locked_idx'])) {
                        $table->index(['is_locked'], 'helpdesk_tickets_is_locked_idx');
                    }
                } catch (\Exception $e) {
                    // Falls Doctrine nicht verfügbar ist, einfach Index hinzufügen
                    $table->index(['is_locked'], 'helpdesk_tickets_is_locked_idx');
                }
            });
        }
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
