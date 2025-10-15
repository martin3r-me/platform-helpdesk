<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" icon="heroicon-o-ticket">
            <x-slot name="titleActions"></x-slot>
            <div class="text-sm text-[var(--ui-muted)]">{{ now()->format('l') }}, {{ now()->format('d.m.Y') }}</div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-info-banner 
            icon="heroicon-o-user"
            title="Persönliche Übersicht"
            message="Deine persönlichen Helpdesk-Tickets und zuständigen Board-Tickets."
            variant="secondary"
        />

        @php
            $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false));
            $openTickets = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
            $completedTickets = $doneGroup?->tasks ?? collect();
            $openStoryPoints = $openTickets->sum(fn($t) => $t->story_points?->points() ?? 0);
            $completedStoryPoints = $completedTickets->sum(fn($t) => $t->story_points?->points() ?? 0);
        @endphp

        

        <x-ui-detail-stats-grid cols="2" gap="6">
            <x-slot:left>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Übersicht</h3>
                <x-ui-form-grid :cols="2" :gap="3">
                    <x-ui-dashboard-tile title="Inbox" :count="$groups->first()?->tasks?->count() ?? 0" icon="inbox" variant="neutral" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$completedTickets->count()" icon="check-circle" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Ohne Fälligkeit" :count="$openTickets->filter(fn($t)=>!$t->due_date)->count()" icon="calendar" variant="neutral" size="sm" />
                    <x-ui-dashboard-tile title="Überfällig" :count="$openTickets->filter(fn($t)=>$t->due_date && $t->due_date->isPast())->count()" icon="exclamation-circle" variant="danger" size="sm" />
                </x-ui-form-grid>
            </x-slot:left>
            <x-slot:right>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Story Points</h3>
                <x-ui-form-grid :cols="2" :gap="3">
                    <x-ui-dashboard-tile title="Offen" :count="$openStoryPoints" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$completedStoryPoints" icon="check-circle" variant="success" size="sm" />
                </x-ui-form-grid>
            </x-slot:right>
        </x-ui-detail-stats-grid>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellstatistiken" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                @php
                    $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false));
                    $openTickets = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
                    $completedTickets = $doneGroup?->tasks ?? collect();
                    $openStoryPoints = $openTickets->sum(fn($t) => $t->story_points?->points() ?? 0);
                    $completedStoryPoints = $completedTickets->sum(fn($t) => $t->story_points?->points() ?? 0);
                    $groupCount = $groups->filter(fn($g)=>!($g->isInbox ?? false) && !($g->isDoneGroup ?? false))->count();
                @endphp
                <div class="grid grid-cols-1 gap-3">
                    <x-ui-dashboard-tile title="Offene Tickets" :count="$openTickets->count()" subtitle="gesamt" icon="clock" variant="secondary" size="lg" />
                    <x-ui-dashboard-tile title="Erledigte Tickets" :count="$completedTickets->count()" subtitle="diesen Monat" icon="check-circle" variant="secondary" size="lg" />
                    <x-ui-dashboard-tile title="Story Points" :count="$openStoryPoints" subtitle="erledigt: {{ $completedStoryPoints }}" icon="chart-bar" variant="secondary" size="lg" />
                    <x-ui-dashboard-tile title="Gruppen" :count="$groupCount" subtitle="inkl. Inbox/Done separat" icon="folder" variant="secondary" size="lg" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Meine Tickets geladen</div>
                        <div class="text-[var(--ui-muted)]">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

</x-ui-page>
