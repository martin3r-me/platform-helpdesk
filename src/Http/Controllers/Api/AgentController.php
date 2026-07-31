<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardCategory;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Models\HelpdeskTicket;

/**
 * Frischer Agent-Flow fürs Helpdesk (Support-/Triage-Worker) — bewusst NEU, unabhängig
 * vom alten GithubRepository-Ticket-Flow. Gleicher Aufbau wie dev/planner: token-
 * authentifizierte Endpunkte, vom Worker-Cockpit konsumiert.
 */
class AgentController extends Controller
{
    /**
     * GET /api/helpdesk/agent/boards
     * Alle Helpdesk-Boards des Worker-Teams — für die bewusste Board-Auswahl in den Settings.
     */
    public function boards(Request $request): JsonResponse
    {
        $teamId = (int) ($request->user()?->current_team_id ?? 0);

        $boards = HelpdeskBoard::query()
            ->when($teamId > 0, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name', 'team_id']);

        return response()->json([
            'data' => $boards->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'team_id' => $b->team_id,
            ])->all(),
        ]);
    }

    /**
     * POST /api/helpdesk/agent/tickets/next-backlog  { board_ids: [int] }
     * Nächstes NEUES Ticket zum Triagieren: im Backlog (slot_id null), offen, nicht (frisch)
     * gesperrt — aus den angehakten Boards, ältestes zuerst (First-Response-Fairness).
     * Sperrt es und liefert Titel + Mailtext + thread_id.
     */
    public function nextBacklogTicket(Request $request): JsonResponse
    {
        $boardIds = collect($request->input('board_ids', []))->map('intval')->filter()->values()->all();
        if (empty($boardIds)) {
            return response()->json(null, 204);
        }

        $ticket = HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->whereNull('helpdesk_board_slot_id')   // Backlog = keinem Slot zugeordnet
            ->where('is_done', false)
            ->where(function ($q) {
                $q->where('is_locked', false)
                    ->orWhereNull('locked_at')
                    ->orWhere('locked_at', '<', now()->subMinutes(30));
            })
            ->orderBy('created_at')            // FIFO — ältestes neues Ticket zuerst
            ->first();

        if (! $ticket) {
            return response()->json(null, 204);
        }

        $ticket->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by_user_id' => (int) $request->user()?->id,
        ]);

        $thread = $this->resolveTicketThread($ticket);

        // Board-Kategorien (kuratiert) mitliefern — description + examples als semantische
        // Grundlage, damit die Triage eine passende wählen kann (oder bewusst keine).
        $categories = HelpdeskBoardCategory::query()
            ->where('helpdesk_board_id', $ticket->helpdesk_board_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'name', 'description', 'examples'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'examples' => $c->examples,
            ])->all();

        return response()->json(['data' => [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'body' => $ticket->notes ?: $ticket->title,   // der eingegangene Mailtext
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'thread_id' => $thread?->id,
            'from' => $thread?->last_inbound_from_address,
            'categories' => $categories,
            // Retrieval: ähnliche erledigte Tickets + deren Kategorie (stärkstes Signal).
            'similar' => $this->similarSolved($ticket, $ticket->title."\n".$ticket->notes),
        ]]);
    }

    /**
     * POST /api/helpdesk/agent/tickets/{id}/triage  { ack_body }
     * Triage-Commit: raus aus dem Backlog (erster Slot des Boards) + Sperre lösen, und die
     * kurze Eingangsbestätigung (Claude-Text) THREADED an den Absender senden — server-seitig
     * über den vorhandenen PostmarkEmailService. Kategorie wird bewusst (noch) NICHT gesetzt.
     */
    public function triageTicket(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'ack_body' => 'nullable|string|max:10000',
            'story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
            'category_id' => 'nullable|integer',
        ]);

        $ticket = HelpdeskTicket::find($id);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        // Raus aus dem Backlog: in den ersten Slot des Boards (falls vorhanden) + Sperre lösen.
        $firstSlot = HelpdeskBoardSlot::where('helpdesk_board_id', $ticket->helpdesk_board_id)
            ->orderBy('order')->first();

        $update = [
            'helpdesk_board_slot_id' => $firstSlot?->id ?? $ticket->helpdesk_board_slot_id,
            'is_locked' => false,
            'locked_at' => null,
            'locked_by_user_id' => null,
        ];

        // Story Points (Claude-Schätzung).
        if (! empty($data['story_points'])) {
            $update['story_points'] = $data['story_points'];
        }

        // Kategorie (Claude-Wahl) — nur setzen, wenn sie zu DIESEM Board gehört (kein
        // Einhängen fremder Kategorien). Leer/„keine passt" → Kategorie bleibt offen.
        if (! empty($data['category_id'])) {
            $belongs = HelpdeskBoardCategory::where('id', $data['category_id'])
                ->where('helpdesk_board_id', $ticket->helpdesk_board_id)->exists();
            if ($belongs) {
                $update['helpdesk_board_category_id'] = (int) $data['category_id'];
            }
        }

        // Fälligkeit aus der Board-SLA: created_at + resolution_time_hours (nur setzen, wenn
        // eine aktive SLA mit Lösungszeit existiert und noch keine Fälligkeit gesetzt ist).
        $sla = $ticket->helpdeskBoard?->sla;
        if (! $ticket->due_date && $sla && $sla->is_active && (int) $sla->resolution_time_hours > 0) {
            $update['due_date'] = ($ticket->created_at ?? now())->copy()->addHours((int) $sla->resolution_time_hours);
        }

        $ticket->update($update);

        // Ack-Mail threaded senden (nur wenn ein Mail-Thread am Ticket hängt und Text da ist).
        $sent = false;
        $ackBody = trim((string) ($data['ack_body'] ?? ''));
        if ($ackBody !== '') {
            $sent = $this->sendThreadReply($ticket, $ackBody, $request);
        }

        $ticket->refresh();
        $ticket->logActivity('Triage: aus dem Backlog geholt'
            .($ticket->category ? ' · '.$ticket->category->name : '')
            .($update['story_points'] ?? null ? ' · '.$update['story_points'].' SP' : '')
            .(isset($update['due_date']) ? ' · fällig '.$ticket->due_date?->toDateString() : '')
            .($sent ? ' · Eingangsbestätigung gesendet' : '').'.', [
                'source' => 'agent', 'status' => 'triaged',
            ]);

        return response()->json(['data' => [
            'id' => $ticket->id,
            'mail_sent' => $sent,
            'slot_id' => $ticket->helpdesk_board_slot_id,
            'story_points' => $ticket->story_points?->value ?? $ticket->story_points,
            'due_date' => $ticket->due_date?->toDateString(),
            'category_id' => $ticket->helpdesk_board_category_id,
            'category' => $ticket->category?->name,
        ]]);
    }

    /**
     * POST /api/helpdesk/agent/boards/{board}/index-solved
     * Embeddet alle ERLEDIGTEN + KATEGORISIERTEN Tickets eines Boards in den Vektor-Store
     * (core EmbeddingService, In-DB), scoped pro Board. Metadata trägt die Kategorie → die
     * Triage bekommt später die nächsten Nachbarn samt Kategorie. Idempotent (skip-if-unchanged).
     */
    public function indexSolvedTickets(Request $request, int $boardId): JsonResponse
    {
        $teamId = (int) ($request->user()?->current_team_id ?? 0);
        $board = HelpdeskBoard::where('id', $boardId)->when($teamId > 0, fn ($q) => $q->where('team_id', $teamId))->first();
        if (! $board) {
            return response()->json(['message' => 'Board not found'], 404);
        }

        $tickets = HelpdeskTicket::query()
            ->where('helpdesk_board_id', $boardId)
            ->where('is_done', true)
            ->whereNotNull('helpdesk_board_category_id')
            ->with('category')
            ->get();

        $svc = app(\Platform\Helpdesk\Services\TicketRetrievalService::class);
        $counts = ['embedded' => 0, 'covered' => 0, 'skipped' => 0, 'error' => 0];
        foreach ($tickets as $t) {
            $result = $svc->indexIfNovel($t);
            $counts[$result] = ($counts[$result] ?? 0) + 1;
        }

        return response()->json(['data' => [
            'board_id' => $boardId,
            'candidates' => $tickets->count(),
        ] + $counts]);
    }

    /**
     * Nächste Nachbarn (ähnliche erledigte Tickets) eines Textes im Board-Index + ihre Kategorie
     * — das stärkste Signal für die Triage. Fehler/kein Provider → leer (Retrieval ist optional).
     *
     * @return array<int, array{title:?string, category:?string, score:float}>
     */
    protected function similarSolved(HelpdeskTicket $ticket, string $text): array
    {
        return app(\Platform\Helpdesk\Services\TicketRetrievalService::class)->similar($ticket, $text);
    }

    /** Den E-Mail-Thread eines Tickets auflösen (Pivot bevorzugt, Fallback Legacy-Spalten). */
    protected function resolveTicketThread(HelpdeskTicket $ticket): ?\Platform\Crm\Models\CommsEmailThread
    {
        // WICHTIG: der Inbound-Listener speichert context_model als getMorphClass()
        // (Morph-Alias 'helpdesk.ticket'), NICHT als FQCN — hier genauso matchen.
        $morph = $ticket->getMorphClass();

        $ctx = \Platform\Crm\Models\CommsThreadContext::query()
            ->where('thread_type', \Platform\Crm\Models\CommsEmailThread::class)
            ->where('context_model', $morph)
            ->where('context_model_id', $ticket->id)
            ->latest('id')->first();

        if ($ctx) {
            return \Platform\Crm\Models\CommsEmailThread::find($ctx->thread_id);
        }

        return \Platform\Crm\Models\CommsEmailThread::query()
            ->where('context_model', $morph)
            ->where('context_model_id', $ticket->id)
            ->latest('id')->first();
    }

    /** Threaded-Reply über den vorhandenen PostmarkEmailService (Banner/Zitat macht der Service). */
    protected function sendThreadReply(HelpdeskTicket $ticket, string $body, Request $request): bool
    {
        $thread = $this->resolveTicketThread($ticket);
        if (! $thread) {
            return false;
        }
        $channel = \Platform\Crm\Models\CommsChannel::find($thread->comms_channel_id);
        $to = (string) ($thread->last_inbound_from_address ?? '');
        if (! $channel || $to === '') {
            Log::warning('[Helpdesk Agent] Triage-Mail: kein Channel/Empfänger', ['ticket_id' => $ticket->id]);

            return false;
        }

        $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size:14px; color:#111;">'
            .nl2br($escaped).'</div>';

        try {
            app(\Platform\Crm\Services\Comms\PostmarkEmailService::class)->send(
                $channel,
                $to,
                (string) ($thread->subject ?: 'Ihre Anfrage'),
                $html,
                null,
                [],
                ['sender' => $request->user(), 'token' => $thread->token, 'is_reply' => true],
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk Agent] Triage-Mail fehlgeschlagen: '.$e->getMessage(), ['ticket_id' => $ticket->id]);

            return false;
        }
    }
}
