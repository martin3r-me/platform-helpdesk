<?php

namespace Platform\Helpdesk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Services\HelpdeskBoardSnapshotService;

class BuildBoardSnapshotsCommand extends Command
{
    protected $signature = 'helpdesk:build-board-snapshots
                            {--board= : Optional einzelne Board-ID}
                            {--team= : Optional auf ein Team beschraenken}
                            {--trigger=cron : Snapshot-Trigger-Label (cron|manual|backfill)}';

    protected $description = 'Erstellt fuer alle Helpdesk-Boards einen Tages-Snapshot (max 1/Tag/Board).';

    public function handle(HelpdeskBoardSnapshotService $service): int
    {
        $query = HelpdeskBoard::query();

        if ($boardId = $this->option('board')) {
            $query->where('id', $boardId);
        }
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $trigger = (string) ($this->option('trigger') ?? 'cron');

        $boards = $query->get();
        $total = $boards->count();

        if ($total === 0) {
            $this->info('Keine Helpdesk-Boards gefunden.');
            return self::SUCCESS;
        }

        $this->info("Snapshotte {$total} Board(s) — Trigger: {$trigger}");

        $ok = 0;
        $failed = 0;

        foreach ($boards as $board) {
            try {
                $snapshot = $service->snapshot($board, $trigger);
                $ok++;
                $this->line(sprintf(
                    '  ✓ #%d %s — health=%s (%s), confidence=%d',
                    $board->id,
                    mb_substr((string) ($board->name ?? '—'), 0, 60),
                    $snapshot->health_score ?? '–',
                    $snapshot->health_color ?? 'gray',
                    $snapshot->confidence_score,
                ));
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf(
                    '  ✗ #%d %s — %s',
                    $board->id,
                    mb_substr((string) ($board->name ?? '—'), 0, 60),
                    $e->getMessage(),
                ));
                Log::error('[helpdesk:build-board-snapshots] Snapshot fehlgeschlagen', [
                    'board_id' => $board->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Fertig: {$ok} OK, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
