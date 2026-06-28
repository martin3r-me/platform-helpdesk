<?php

namespace Platform\Helpdesk\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;

class HealthIndex extends Component
{
    /** @var string all|red|yellow|green|gray */
    public string $colorFilter = 'all';

    /** @var string all|backlog|sla|escalation|workload */
    public string $axisFilter = 'all';

    /** @var string all|with_sla|without_sla */
    public string $slaFilter = 'all';

    /** @var string worst|best|movement|confidence|name */
    public string $sort = 'worst';

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Juengsten Snapshot pro Board im aktuellen Team
        $latestIds = DB::table('helpdesk_board_snapshots as a')
            ->where('a.team_id', $team->id)
            ->whereRaw('a.taken_on = (
                SELECT MAX(b.taken_on) FROM helpdesk_board_snapshots b
                WHERE b.helpdesk_board_id = a.helpdesk_board_id
            )')
            ->pluck('a.id');

        $all = HelpdeskBoardSnapshot::with(['board:id,name'])
            ->whereIn('id', $latestIds)
            ->get();

        $totalAll = $all->count();

        // KPIs / Verteilungen ueber den vollen Scope
        $byColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
        $byAxis = ['backlog' => 0, 'sla' => 0, 'escalation' => 0, 'workload' => 0];
        $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
        $slaCoverage = ['with' => 0, 'without' => 0];

        foreach ($all as $s) {
            $byColor[$s->health_color ?: 'gray'] = ($byColor[$s->health_color ?: 'gray'] ?? 0) + 1;
            if ($s->worst_axis && isset($byAxis[$s->worst_axis])) {
                $byAxis[$s->worst_axis]++;
            }
            $c = (int) $s->confidence_score;
            if ($c >= 75) $byConfidence['high_75_100']++;
            elseif ($c >= 50) $byConfidence['medium_50_74']++;
            elseif ($c >= 25) $byConfidence['low_25_49']++;
            else $byConfidence['none_0_24']++;

            if ($s->has_sla) $slaCoverage['with']++; else $slaCoverage['without']++;
        }

        // Filter
        $filtered = $all;
        if ($this->colorFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => ($s->health_color ?: 'gray') === $this->colorFilter);
        }
        if ($this->axisFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => $s->worst_axis === $this->axisFilter);
        }
        if ($this->slaFilter === 'with_sla') {
            $filtered = $filtered->filter(fn ($s) => (bool) $s->has_sla);
        } elseif ($this->slaFilter === 'without_sla') {
            $filtered = $filtered->filter(fn ($s) => ! $s->has_sla);
        }

        // Sortierung
        $colorRank = ['red' => 0, 'yellow' => 1, 'gray' => 2, 'green' => 3];
        $filtered = match ($this->sort) {
            'best' => $filtered->sortByDesc(fn ($s) => $s->health_score ?? -1)->values(),
            'movement' => $filtered->sortByDesc(fn ($s) => $s->last_movement_at?->timestamp ?? 0)->values(),
            'confidence' => $filtered->sortBy('confidence_score')->values(),
            'name' => $filtered->sortBy(fn ($s) => mb_strtolower($s->board?->name ?? ''))->values(),
            default => $filtered->sort(function ($a, $b) use ($colorRank) {
                $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                if ($ra !== $rb) return $ra <=> $rb;
                return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
            })->values(),
        };

        // Bewegung
        $withDelta = $all->filter(fn ($s) => $s->delta_health_score !== null && $s->delta_health_score !== 0);
        $topGainers = $withDelta->filter(fn ($s) => $s->delta_health_score > 0)->sortByDesc('delta_health_score')->take(5)->values();
        $topLosers = $withDelta->filter(fn ($s) => $s->delta_health_score < 0)->sortBy('delta_health_score')->take(5)->values();

        return view('helpdesk::livewire.health-index', [
            'team' => $team,
            'totalAll' => $totalAll,
            'byColor' => $byColor,
            'byAxis' => $byAxis,
            'byConfidence' => $byConfidence,
            'slaCoverage' => $slaCoverage,
            'snapshots' => $filtered,
            'lastTakenOn' => $all->max('taken_on'),
            'topGainers' => $topGainers,
            'topLosers' => $topLosers,
            'movedBoardsCount' => $withDelta->count(),
        ]);
    }
}
