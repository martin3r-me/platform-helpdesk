<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

    /**
     * POST /api/helpdesk/agent/tickets/next-triaged { board_ids }
     * Nächstes TRIAGIERTES Ticket (raus aus Backlog: Slot gesetzt, nicht erledigt, nicht
     * gesperrt) der angehakten Boards — für den Supporter. Nach Fälligkeit, dann Alter.
     * Liefert Ticket + Kategorie + Resolution-Retrieval (KB-Lösungen + ähnliche Tickets).
     */
    public function nextTriagedTicket(Request $request): JsonResponse
    {
        $boardIds = collect($request->input('board_ids', []))->map('intval')->filter()->values()->all();
        if (empty($boardIds)) {
            return response()->json(null, 204);
        }

        $workerId = (int) $request->user()?->id;

        // Resume-First (beides setzt die geparkte Claude-Session fort, kein Reassign):
        //  1) Freigabe-Loop: Supervisor hat den Vorschlag im Kontext-Thread beantwortet.
        //  2) Kunden-Rückfrage: Kunde hat per E-Mail auf eine Rückfrage geantwortet.
        if ($resume = $this->resumableApproval($boardIds, $workerId)) {
            $msg = $this->supervisorReplyText($resume, $workerId);
            $resume->update([
                'agent_waiting_at' => null, 'agent_waiting_kind' => null,
                'is_locked' => true, 'locked_at' => now(), 'locked_by_user_id' => $workerId,
            ]);

            return response()->json(['data' => $this->triagedPayload($resume, true, 'approval', $msg)]);
        }
        if ($resume = $this->resumableTicket($boardIds)) {
            $msg = $this->latestInboundText($this->resolveTicketThread($resume));
            $resume->update([
                'agent_waiting_at' => null, 'agent_waiting_kind' => null,
                'is_locked' => true, 'locked_at' => now(), 'locked_by_user_id' => $workerId,
            ]);

            return response()->json(['data' => $this->triagedPayload($resume, true, 'customer', $msg)]);
        }

        $ticket = HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->whereNotNull('helpdesk_board_slot_id')   // triagiert = keinem Backlog mehr
            ->where('is_done', false)
            ->whereNull('agent_waiting_at')            // auf Kundenantwort wartende überspringen (Resume-Pass holt sie)
            ->whereNull('agent_handled_at')            // bereits vom Worker behandelt (Vorschlag/Eskalation) → Mensch ist dran
            ->where(function ($q) {
                $q->where('is_locked', false)->orWhereNull('locked_at')->orWhere('locked_at', '<', now()->subMinutes(30));
            })
            ->orderByRaw('due_date IS NULL')            // Fälligkeit zuerst, NULL zuletzt
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->with('category')
            ->first();

        if (! $ticket) {
            return response()->json(null, 204);
        }

        $ticket->update(['is_locked' => true, 'locked_at' => now(), 'locked_by_user_id' => $workerId]);

        return response()->json(['data' => $this->triagedPayload($ticket, false)]);
    }

    /**
     * Einheitliches Payload für next-triaged (Erst-Claim + Resume). Bei $resume=true weiß der
     * Worker, dass er die gemerkte Claude-Session fortsetzt; `customer_reply` ist die neue
     * Kundenantwort (aus der letzten Inbound-Mail), die die Rückfrage beantwortet.
     *
     * @return array<string, mixed>
     */
    protected function triagedPayload(HelpdeskTicket $ticket, bool $resume, string $resumeKind = 'customer', ?string $resumeMessage = null): array
    {
        $ticket->loadMissing('category');
        $thread = $this->resolveTicketThread($ticket);
        $svc = app(\Platform\Helpdesk\Services\TicketRetrievalService::class);
        $text = $ticket->title."\n".$ticket->notes;

        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'body' => $ticket->notes ?: $ticket->title,
            'category' => $ticket->category?->name,
            'due_date' => $ticket->due_date?->toDateString(),
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'thread_id' => $thread?->id,
            'from' => $thread?->last_inbound_from_address,
            'resolutions' => $svc->resolutions($ticket, $text),
            // Resume-Signal: gemerkte Session + Art (customer=Kundenantwort / approval=Supervisor-OK)
            // + die auslösende Nachricht.
            'resume' => $resume,
            'resume_kind' => $resume ? $resumeKind : null,
            'agent_session_id' => $resume ? $ticket->agent_session_id : null,
            'resume_message' => $resume ? $resumeMessage : null,
        ];
    }

    /**
     * Ein auf Kundenantwort wartendes Ticket (agent_waiting_at) in diesen Boards, dessen
     * E-Mail-Thread seit dem Warten eine neue Inbound-Mail bekommen hat — ältestes zuerst.
     */
    protected function resumableTicket(array $boardIds): ?HelpdeskTicket
    {
        $morph = (new HelpdeskTicket)->getMorphClass();

        return HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->where('is_done', false)
            ->whereNotNull('agent_session_id')
            ->whereNotNull('agent_waiting_at')
            ->whereExists(function ($q) use ($morph) {
                $q->select(DB::raw(1))
                    ->from('comms_thread_contexts as ctx')
                    ->join('comms_email_threads as t', 't.id', '=', 'ctx.thread_id')
                    ->where('ctx.thread_type', \Platform\Crm\Models\CommsEmailThread::class)
                    ->where('ctx.context_model', $morph)
                    ->whereColumn('ctx.context_model_id', 'helpdesk_tickets.id')
                    ->whereColumn('t.last_inbound_at', '>', 'helpdesk_tickets.agent_waiting_at');
            })
            ->orderBy('agent_waiting_at')
            ->with('category')
            ->first();
    }

    /**
     * Ein auf Supervisor-Freigabe wartendes Ticket (agent_waiting_kind='approval'), dessen
     * Kontext-Thread seit dem Warten eine Nachricht von jemand ANDEREM als dem Worker bekam
     * (= das OK/der Kommentar des Supervisors). Kontext-Channel wie PostContextMessage ihn
     * anlegt: context_type = HelpdeskTicket-FQCN.
     */
    protected function resumableApproval(array $boardIds, int $workerId): ?HelpdeskTicket
    {
        $ctxType = HelpdeskTicket::class;

        return HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->where('is_done', false)
            ->where('agent_waiting_kind', 'approval')
            ->whereNotNull('agent_session_id')
            ->whereNotNull('agent_waiting_at')
            ->whereExists(function ($q) use ($ctxType, $workerId) {
                $q->select(DB::raw(1))
                    ->from('terminal_messages as tm')
                    ->join('terminal_channels as tc', 'tm.channel_id', '=', 'tc.id')
                    ->where('tc.context_type', $ctxType)
                    ->whereColumn('tc.context_id', 'helpdesk_tickets.id')
                    ->where('tm.user_id', '!=', $workerId)
                    ->whereColumn('tm.created_at', '>', 'helpdesk_tickets.agent_waiting_at');
            })
            ->orderBy('agent_waiting_at')
            ->with('category')
            ->first();
    }

    /** Jüngste Supervisor-Nachricht im Kontext-Thread seit dem Warten (das Freigabe-OK). */
    protected function supervisorReplyText(HelpdeskTicket $ticket, int $workerId): ?string
    {
        $body = \Platform\Core\Models\TerminalMessage::query()
            ->join('terminal_channels as tc', 'terminal_messages.channel_id', '=', 'tc.id')
            ->where('tc.context_type', HelpdeskTicket::class)
            ->where('tc.context_id', $ticket->id)
            ->where('terminal_messages.user_id', '!=', $workerId)
            ->where('terminal_messages.created_at', '>', $ticket->agent_waiting_at)
            ->orderByDesc('terminal_messages.id')
            ->value('terminal_messages.body_plain');

        $t = trim((string) $body);

        return $t !== '' ? mb_substr($t, 0, 5000) : null;
    }

    /** Text der letzten Inbound-Mail eines Threads (Kundenantwort auf die Rückfrage). */
    protected function latestInboundText(?\Platform\Crm\Models\CommsEmailThread $thread): ?string
    {
        if (! $thread) {
            return null;
        }
        $mail = \Platform\Crm\Models\CommsEmailInboundMail::query()
            ->where('thread_id', $thread->id)
            ->latest('id')->first();

        $text = trim((string) ($mail?->text_body ?? ''));

        return $text !== '' ? mb_substr($text, 0, 5000) : null;
    }

    /**
     * POST /api/helpdesk/agent/tickets/{id}/resolve
     *   { action: reply_close|propose|escalate, reply_body?, kb?:{problem,solution}, note? }
     * Führt die vom Supporter (nach Modus) gewählte Aktion aus:
     *  - reply_close: threaded Antwort an den Kunden + erledigt (+ optional KB-Eintrag).
     *  - propose:     Entwurf als interne Notiz ans Ticket (kein Mailversand, kein Schließen).
     *  - escalate:    Eskalation setzen + Notiz.
     */
    public function resolveTicket(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:reply_close,propose,escalate,ask',
            'reply_body' => 'nullable|string|max:20000',
            'note' => 'nullable|string|max:5000',
            'resolution' => 'nullable|string|max:20000',
            'session_id' => 'nullable|string|max:255',
            'kb' => 'nullable|array',
            'kb.problem' => 'nullable|string|max:10000',
            'kb.solution' => 'nullable|string|max:20000',
        ]);

        $ticket = HelpdeskTicket::find($id);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        $action = $data['action'];
        $sent = false;

        if ($action === 'ask') {
            // Rückfrage an den Kunden: Frage als threaded Antwort raus, Ticket in den
            // Warten-Zustand (agent_waiting_at) + Session merken. Owner bleibt der Worker;
            // der normale Claim überspringt es, bis eine Kundenantwort eintrifft (Resume).
            $question = trim((string) ($data['reply_body'] ?? ''));
            if ($question !== '') {
                $sent = $this->sendThreadReply($ticket, $question, $request);
            }
            $ticket->update([
                'agent_waiting_at' => now(),
                'agent_waiting_kind' => 'customer',
                'agent_session_id' => $data['session_id'] ?? $ticket->agent_session_id,
                'is_locked' => false, 'locked_at' => null, 'locked_by_user_id' => null,
                'helpdesk_board_slot_id' => $this->slotFor($ticket, 'waiting') ?? $ticket->helpdesk_board_slot_id,
            ]);
            $ticket->logActivity('Supporter: Rückfrage an Kunde gestellt — wartet auf Antwort.', ['source' => 'agent', 'status' => 'waiting']);

            return response()->json(['data' => ['id' => $ticket->id, 'action' => 'ask', 'mail_sent' => $sent, 'is_done' => false]]);
        }

        if ($action === 'reply_close') {
            $body = trim((string) ($data['reply_body'] ?? ''));
            if ($body !== '') {
                $sent = $this->sendThreadReply($ticket, $body, $request);
            }
            $ticket->update([
                'is_done' => true,
                'done_at' => now(),
                // Prozess-bewusste Lösung (aus dem vollen Verlauf) → beim Auto-Index als
                // solution-Metadatum ins Retrieval (indexIfNovel liest $ticket->resolution).
                'resolution' => trim((string) ($data['resolution'] ?? '')) ?: $ticket->resolution,
                'helpdesk_board_slot_id' => $this->slotFor($ticket, 'solved') ?? $ticket->helpdesk_board_slot_id,
                'is_locked' => false, 'locked_at' => null, 'locked_by_user_id' => null,
                'agent_waiting_at' => null, 'agent_waiting_kind' => null, 'agent_session_id' => null,
            ]);
            // KB-Eintrag (kuratierte Lösung) — nur wenn Problem+Lösung geliefert.
            if (! empty($data['kb']['problem']) && ! empty($data['kb']['solution'])) {
                \Platform\Helpdesk\Models\HelpdeskKnowledgeEntry::create([
                    'helpdesk_board_id' => $ticket->helpdesk_board_id,
                    'title' => \Illuminate\Support\Str::limit($ticket->title, 120, ''),
                    'problem' => $data['kb']['problem'],
                    'solution' => $data['kb']['solution'],
                    'source_ticket_id' => $ticket->id,
                ]);
            }
            $ticket->logActivity('Supporter: gelöst'.($sent ? ' + Antwort gesendet' : '').'.', ['source' => 'agent', 'status' => 'resolved']);
        } elseif ($action === 'propose') {
            // Freigabe-Loop: Entwurf in den Thread + WARTEN auf das OK des Supervisors dort.
            // Antwortet er (z. B. „passt, senden" oder Änderungswunsch), holt der Resume-Pass
            // (resumableApproval) das Ticket zurück und der Worker setzt genau diese Session fort.
            $draft = trim((string) ($data['reply_body'] ?? ''));
            $ticket->update(['is_locked' => false, 'locked_at' => null, 'locked_by_user_id' => null,
                'agent_waiting_at' => now(), 'agent_waiting_kind' => 'approval',
                'agent_session_id' => $data['session_id'] ?? $ticket->agent_session_id,
                'helpdesk_board_slot_id' => $this->slotFor($ticket, 'waiting') ?? $ticket->helpdesk_board_slot_id]);
            // Nur @Mention (KEIN Reassign): der Worker bleibt Owner/Problemlöser und wartet
            // auf die Freigabe — wie eine Rückfrage. Verantwortlicher wechselt nur bei escalate.
            $this->handToHuman($ticket, (int) $request->user()?->id,
                "Lösungs-Vorschlag des Support-Workers — bitte hier freigeben: antworte „passt/senden\", "
                ."oder nenne die gewünschte Änderung (dann überarbeite ich):\n\n".$draft, assign: false);
            $ticket->logActivity('Supporter: Vorschlag zur Freigabe hinterlegt — wartet auf OK im Thread.', ['source' => 'agent', 'status' => 'proposed']);
        } else { // escalate
            $ticket->update([
                'escalation_level' => 'escalated',
                'escalated_at' => now(),
                'escalation_count' => (int) $ticket->escalation_count + 1,
                'is_locked' => false, 'locked_at' => null, 'locked_by_user_id' => null,
                'agent_waiting_at' => null, 'agent_waiting_kind' => null, 'agent_session_id' => null,
                // an einen Menschen übergeben → nicht erneut vom Worker ziehen + in „In Bearbeitung".
                'agent_handled_at' => now(),
                'helpdesk_board_slot_id' => $this->slotFor($ticket, 'in_progress') ?? $ticket->helpdesk_board_slot_id,
            ]);
            // @Mention + Zuweisung → der Verantwortliche ist jetzt am Ball.
            $this->handToHuman($ticket, (int) $request->user()?->id,
                "Support-Worker hat ESKALIERT — du bist am Ball:\n\n".(! empty($data['note']) ? $data['note'] : '(kein Grund angegeben)'));
            $ticket->logActivity('Supporter: eskaliert.'.(! empty($data['note']) ? "\n\n".$data['note'] : ''), ['source' => 'agent', 'status' => 'escalated']);
        }

        return response()->json(['data' => ['id' => $ticket->id, 'action' => $action, 'mail_sent' => $sent, 'is_done' => (bool) $ticket->is_done]]);
    }

    /** Nachricht in den Kontext-Thread eines Tickets posten (find-or-create) — für Vorschläge. */
    protected function postToTicketThread(HelpdeskTicket $ticket, int $senderId, string $body): void
    {
        try {
            app(\Platform\Core\Services\PostContextMessage::class)
                ->post((int) $ticket->team_id, HelpdeskTicket::class, $ticket->id, $ticket->title, $senderId, $body);
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk Agent] Vorschlag-Thread fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Slot für ein semantisches Ziel auf DIESEM Board — dynamisch aus den AKTUELLEN Slots
     * aufgelöst (nie gespeichert → überlebt Umbenennen/Umsortieren). Kein Treffer → null =
     * Ticket bleibt, wo es ist (nie falsch verschieben). 'solved' fällt auf den letzten Slot.
     */
    protected function slotFor(HelpdeskTicket $ticket, string $purpose): ?int
    {
        $slots = HelpdeskBoardSlot::where('helpdesk_board_id', $ticket->helpdesk_board_id)
            ->orderBy('order')->get(['id', 'name']);
        if ($slots->isEmpty()) {
            return null;
        }
        $re = [
            'solved' => '/gel[öo]st|erledigt|closed|done|fertig|abgeschlossen/i',
            'waiting' => '/wart|wait|hold|pending|pausiert|freigabe/i',
            'in_progress' => '/bearbeit|in arbeit|progress|doing|pr[üu]fung|zugewiesen/i',
        ][$purpose] ?? null;
        if ($re) {
            foreach ($slots as $s) {
                if (preg_match($re, (string) $s->name)) {
                    return (int) $s->id;
                }
            }
        }

        return $purpose === 'solved' ? (int) $slots->last()->id : null;
    }

    /**
     * Benachrichtigung mit @Mention in den Kontext-Thread an den zuständigen Menschen
     * (Board-Owner). $assign=true wechselt zusätzlich die Zuständigkeit — NUR bei echter
     * Übergabe (escalate: Worker kann nicht lösen). Bei Rückfrage/Freigabe bleibt der
     * Worker Owner und Problemlöser (wartet + resumed), analog zu dev/planner — dann nur
     * benachrichtigen, NICHT umhängen. Gibt die adressierte User-ID zurück.
     */
    protected function handToHuman(HelpdeskTicket $ticket, int $senderId, string $body, bool $assign = true): ?int
    {
        $ownerId = (int) ($ticket->user_in_charge_id ?: $ticket->helpdeskBoard?->user_id ?: $ticket->user_id);
        if ($ownerId < 1) {
            return null;
        }
        if ($assign && ! $ticket->user_in_charge_id) {
            $ticket->update(['user_in_charge_id' => $ownerId]);
        }
        try {
            app(\Platform\Core\Services\PostContextMessage::class)->post(
                (int) $ticket->team_id, HelpdeskTicket::class, $ticket->id, $ticket->title,
                $senderId, $body, [$ownerId], [$ownerId] // member + mention → Benachrichtigung
            );
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk Agent] Handoff-Nachricht fehlgeschlagen: '.$e->getMessage());
        }

        return $ownerId;
    }

    /**
     * POST /api/helpdesk/agent/tickets/next-learnable  { board_ids }
     * Learn-Stufe (nächtlich): nächstes ABGESCHLOSSENE Ticket OHNE `resolution` und noch
     * nicht gelernt (learned_at null), kategorisiert. Liefert den vollen Verlauf zum
     * Destillieren. Nebenbei: Slot-Aufräumen (is_done ohne Gelöst-Slot → Gelöst).
     */
    public function nextLearnableTicket(Request $request): JsonResponse
    {
        $boardIds = collect($request->input('board_ids', []))->map('intval')->filter()->values()->all();
        if (empty($boardIds)) {
            return response()->json(null, 204);
        }
        $workerId = (int) $request->user()?->id;

        $this->normalizeSolvedSlots($boardIds);

        $ticket = HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->where('is_done', true)
            ->whereNull('resolution')
            ->whereNull('learned_at')
            ->whereNotNull('helpdesk_board_category_id')
            ->where(function ($q) {
                $q->where('is_locked', false)->orWhereNull('locked_at')->orWhere('locked_at', '<', now()->subMinutes(30));
            })
            ->orderBy('done_at')
            ->with('category')->first();

        if (! $ticket) {
            return response()->json(null, 204);
        }

        $ticket->update(['is_locked' => true, 'locked_at' => now(), 'locked_by_user_id' => $workerId]);

        return response()->json(['data' => [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'category' => $ticket->category?->name,
            'conversation' => $this->conversationText($ticket),
        ]]);
    }

    /**
     * POST /api/helpdesk/agent/tickets/{id}/learn  { resolution? }
     * Ergebnis der Learn-Stufe: resolution gesetzt → als solution-Metadatum in den Index
     * (force, auch wenn das Problem schon embeddet war). Leer = „nichts zu lernen".
     * In beiden Fällen learned_at → nicht erneut sweepen.
     */
    public function learnTicket(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['resolution' => 'nullable|string|max:20000']);
        $ticket = HelpdeskTicket::with('category')->find($id);
        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }
        $resolution = trim((string) ($data['resolution'] ?? ''));
        $ticket->update([
            'resolution' => $resolution ?: null,
            'learned_at' => now(),
            'is_locked' => false, 'locked_at' => null, 'locked_by_user_id' => null,
        ]);
        $indexed = false;
        if ($resolution !== '') {
            try {
                app(\Platform\Helpdesk\Services\TicketRetrievalService::class)->indexIfNovel($ticket->fresh(), force: true);
                $indexed = true;
            } catch (\Throwable $e) {
                Log::warning('[Helpdesk Agent] Learn-Index fehlgeschlagen: '.$e->getMessage());
            }
        }

        return response()->json(['data' => ['id' => $ticket->id, 'learned' => $resolution !== '', 'indexed' => $indexed]]);
    }

    /** is_done-Tickets, die nicht im Gelöst-Slot hängen, dorthin ziehen (Aufräumen). */
    protected function normalizeSolvedSlots(array $boardIds): void
    {
        $tickets = HelpdeskTicket::query()
            ->whereIn('helpdesk_board_id', $boardIds)
            ->where('is_done', true)
            ->limit(50)->get(['id', 'helpdesk_board_id', 'helpdesk_board_slot_id']);
        foreach ($tickets as $t) {
            $solved = $this->slotFor($t, 'solved');
            if ($solved && (int) $t->helpdesk_board_slot_id !== $solved) {
                $t->update(['helpdesk_board_slot_id' => $solved]);
            }
        }
    }

    /** Voller Verlauf eines Tickets fürs Destillieren: Problem + Mail-Thread + interne Notizen. */
    protected function conversationText(HelpdeskTicket $ticket): string
    {
        $parts = ['PROBLEM:'."\n".trim((string) $ticket->notes)];

        $thread = $this->resolveTicketThread($ticket);
        if ($thread) {
            try {
                foreach (\Platform\Crm\Models\CommsEmailInboundMail::where('thread_id', $thread->id)->orderBy('id')->get() as $m) {
                    $b = trim((string) ($m->text_body ?? ''));
                    if ($b !== '') {
                        $parts[] = 'KUNDE:'."\n".mb_substr($b, 0, 2000);
                    }
                }
            } catch (\Throwable $e) {
            }
            try {
                foreach (\Platform\Crm\Models\CommsEmailOutboundMail::where('thread_id', $thread->id)->orderBy('id')->get() as $m) {
                    $b = trim(strip_tags((string) ($m->text_body ?? $m->html_body ?? '')));
                    if ($b !== '') {
                        $parts[] = 'SUPPORT:'."\n".mb_substr($b, 0, 2000);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            $ctx = \Platform\Core\Models\TerminalMessage::query()
                ->join('terminal_channels as tc', 'terminal_messages.channel_id', '=', 'tc.id')
                ->where('tc.context_type', HelpdeskTicket::class)
                ->where('tc.context_id', $ticket->id)
                ->orderBy('terminal_messages.id')->limit(30)
                ->pluck('terminal_messages.body_plain');
            foreach ($ctx as $b) {
                $b = trim((string) $b);
                if ($b !== '') {
                    $parts[] = 'INTERN:'."\n".mb_substr($b, 0, 1500);
                }
            }
        } catch (\Throwable $e) {
        }

        return mb_substr(implode("\n\n", $parts), 0, 8000);
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
