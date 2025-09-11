<?php

namespace Platform\Helpdesk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Services\TicketEscalationService;

class CheckTicketEscalationsCommand extends Command
{
    protected $signature = 'helpdesk:check-escalations 
                            {--board= : Nur bestimmtes Board prüfen}
                            {--dry-run : Nur prüfen, keine Aktionen ausführen}
                            {--detailed : Detaillierte Ausgabe}';

    protected $description = 'Prüft alle offenen Tickets auf Eskalations-Bedarf';

    public function handle(TicketEscalationService $escalationService): int
    {
        $this->info('🚀 Starte Ticket-Eskalations-Prüfung...');
        
        $startTime = microtime(true);
        $boardId = $this->option('board');
        $isDryRun = $this->option('dry-run');
        $isDetailed = $this->option('detailed');

        if ($isDryRun) {
            $this->warn('🔍 DRY-RUN Modus: Keine Aktionen werden ausgeführt');
        }

        try {
            if ($boardId) {
                $this->info("📋 Prüfe nur Board ID: {$boardId}");
                $result = $this->checkBoardEscalations($escalationService, $boardId, $isDryRun, $isDetailed);
            } else {
                $this->info('📋 Prüfe alle Boards...');
                $result = $this->checkAllEscalations($escalationService, $isDryRun, $isDetailed);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("✅ Eskalations-Prüfung abgeschlossen in {$duration}ms");
            
            if ($result['escalated'] > 0) {
                $this->warn("⚠️  {$result['escalated']} Tickets eskaliert");
            } else {
                $this->info("✅ Keine Eskalationen nötig");
            }

            if ($isDetailed) {
                $this->table(
                    ['Metrik', 'Wert'],
                    [
                        ['Geprüfte Tickets', $result['checked']],
                        ['Eskaliert', $result['escalated']],
                        ['Dauer (ms)', $duration],
                    ]
                );
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Fehler bei Eskalations-Prüfung: {$e->getMessage()}");
            
            if ($isDetailed) {
                $this->error($e->getTraceAsString());
            }

            Log::error('Ticket escalation check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function checkAllEscalations(TicketEscalationService $escalationService, bool $isDryRun, bool $isDetailed): array
    {
        $checked = 0;
        $escalated = 0;

        // Alle Boards mit aktiven SLAs finden
        $boards = \Platform\Helpdesk\Models\HelpdeskBoard::query()
            ->whereHas('sla', function ($query) {
                $query->where('is_active', true);
            })
            ->with(['sla'])
            ->get();

        if ($isDetailed) {
            $this->info("📊 Gefunden: {$boards->count()} Boards mit aktiven SLAs");
        }

        if ($boards->count() === 0) {
            $this->warn("⚠️  Keine Boards mit aktiven SLAs gefunden!");
            $this->info("💡 Tipp: Erstelle eine SLA und weise sie einem Board zu");
        }

        foreach ($boards as $board) {
            $boardResult = $this->checkBoardEscalations($escalationService, $board->id, $isDryRun, $isDetailed);
            $checked += $boardResult['checked'];
            $escalated += $boardResult['escalated'];
        }

        return compact('checked', 'escalated');
    }

    private function checkBoardEscalations(TicketEscalationService $escalationService, $boardId, bool $isDryRun, bool $isDetailed): array
    {
        $board = \Platform\Helpdesk\Models\HelpdeskBoard::find($boardId);
        
        if (!$board) {
            $this->error("❌ Board ID {$boardId} nicht gefunden");
            return ['checked' => 0, 'escalated' => 0];
        }

        $tickets = $board->tickets()
            ->where('is_done', false)
            ->with(['helpdeskBoard.sla', 'userInCharge', 'escalations'])
            ->get();

        $checked = $tickets->count();
        $escalated = 0;

        if ($isDetailed) {
            $this->info("📋 Board '{$board->name}': {$checked} offene Tickets");
        }

        foreach ($tickets as $ticket) {
            if ($isDetailed) {
                $this->info("🔍 Prüfe Ticket {$ticket->id}: {$ticket->title}");
                $this->info("   - Board: {$ticket->helpdeskBoard->name}");
                $this->info("   - SLA: " . ($ticket->helpdeskBoard->sla ? $ticket->helpdeskBoard->sla->name : 'KEINE'));
                $this->info("   - Erstellt: {$ticket->created_at->diffForHumans()}");
                
                if ($ticket->helpdeskBoard->sla) {
                    $remainingTime = $ticket->helpdeskBoard->sla->getRemainingTime($ticket);
                    $this->info("   - Restzeit: " . ($remainingTime !== null ? "{$remainingTime}h" : "N/A"));
                    $this->info("   - Eskalations-Level: " . $ticket->helpdeskBoard->sla->getEscalationLevel($ticket)->value);
                }
            }
            
            if ($ticket->helpdeskBoard->sla && $ticket->helpdeskBoard->sla->needsEscalation($ticket)) {
                if (!$isDryRun) {
                    $escalationService->checkTicketEscalation($ticket);
                }
                
                $escalated++;
                
                if ($isDetailed) {
                    $this->warn("⚠️  Ticket {$ticket->id} ({$ticket->title}) eskaliert");
                }
            }
        }

        return compact('checked', 'escalated');
    }


}
