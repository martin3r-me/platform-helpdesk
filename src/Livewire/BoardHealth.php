<?php

namespace Platform\Helpdesk\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;
use Platform\Helpdesk\Services\HelpdeskBoardSnapshotService;

class BoardHealth extends Component
{
    public HelpdeskBoard $board;

    public int $trendDays = 30;

    public function mount(HelpdeskBoard $helpdeskBoard): void
    {
        $this->authorize('view', $helpdeskBoard);
        $this->board = $helpdeskBoard;
    }

    public function setTrendDays(int $days): void
    {
        $this->trendDays = max(7, min(180, $days));
    }

    public function refreshSnapshot(HelpdeskBoardSnapshotService $service): void
    {
        $this->authorize('view', $this->board);
        $service->snapshot($this->board, 'manual');

        $this->dispatch('notifications:store', [
            'title' => 'Snapshot aktualisiert',
            'message' => 'Der Health-Stand wurde gerade neu berechnet.',
            'notice_type' => 'success',
            'noticable_type' => HelpdeskBoard::class,
            'noticable_id' => $this->board->id,
        ]);
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $latest = HelpdeskBoardSnapshot::with(['topTickets', 'people', 'slots'])
            ->where('helpdesk_board_id', $this->board->id)
            ->orderByDesc('taken_on')
            ->first();

        $from = Carbon::now()->subDays($this->trendDays - 1)->toDateString();
        $to = Carbon::now()->toDateString();

        $trend = HelpdeskBoardSnapshot::where('helpdesk_board_id', $this->board->id)
            ->whereBetween('taken_on', [$from, $to])
            ->orderBy('taken_on')
            ->get([
                'id', 'taken_on',
                'health_score', 'health_color', 'worst_axis', 'axis_scores',
                'confidence_score',
                'tickets_open', 'tickets_done', 'tickets_overdue',
                'tickets_escalated', 'tickets_critical',
                'tickets_breaching_resolution',
                'story_points_open', 'story_points_done',
            ]);

        return view('helpdesk::livewire.board-health', [
            'board' => $this->board,
            'latest' => $latest,
            'trend' => $trend,
            'trendFrom' => $from,
            'trendTo' => $to,
        ]);
    }
}
