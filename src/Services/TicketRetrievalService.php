<?php

namespace Platform\Helpdesk\Services;

use Platform\Core\Services\EmbeddingService;
use Platform\Helpdesk\Models\HelpdeskTicket;

/**
 * Kategorie-Retrieval fürs Helpdesk (In-DB über core EmbeddingService, kein externer
 * Vektor-Store). Eine Wahrheit für: Board-Index füllen (mit Admission-Gate = Covering-Set)
 * und Nachbarn suchen (für die Triage). Der Index scopet pro Board.
 */
class TicketRetrievalService
{
    /** Ab dieser Ähnlichkeit gilt ein Ticket als „schon abgedeckt" → nicht aufnehmen (Covering-Set). */
    private const COVER_THRESHOLD = 0.85;

    public function entityType(int $boardId): string
    {
        return "helpdesk_ticket_board_{$boardId}";
    }

    public function ticketText(HelpdeskTicket $t): string
    {
        return trim($t->title."\n\n".(string) $t->notes);
    }

    /**
     * Admission: ein erledigtes+kategorisiertes Ticket NUR aufnehmen, wenn es neue Varianz
     * bringt (kein sehr ähnlicher Anker DERSELBEN Kategorie existiert). Verhindert Bloat durch
     * Fast-Duplikate. Gibt zurück: 'embedded' | 'covered' | 'skipped' | 'error'.
     */
    public function indexIfNovel(HelpdeskTicket $t): string
    {
        if (! $t->is_done || ! $t->helpdesk_board_category_id) {
            return 'skipped';
        }
        $text = $this->ticketText($t);
        if ($text === '') {
            return 'skipped';
        }

        $svc = app(EmbeddingService::class);
        $type = $this->entityType((int) $t->helpdesk_board_id);

        try {
            $hits = $svc->search((int) $t->team_id, $text, [$type], 5, 0.0);
        } catch (\Throwable $e) {
            $hits = [];
        }
        foreach ($hits as $h) {
            if ((string) ($h['entity_id'] ?? '') === (string) $t->id) {
                continue; // sich selbst nicht als „Abdeckung" zählen
            }
            $m = $h['metadata'] ?? [];
            if (! is_array($m)) {
                $m = json_decode((string) $m, true) ?: [];
            }
            if ((float) ($h['score'] ?? 0) >= self::COVER_THRESHOLD
                && (int) ($m['category_id'] ?? 0) === (int) $t->helpdesk_board_category_id) {
                return 'covered';
            }
        }

        try {
            $svc->embedAndStore(
                teamId: (int) $t->team_id,
                entityType: $type,
                entityId: $t->id,
                text: $text,
                metadata: ['category_id' => $t->helpdesk_board_category_id, 'category' => $t->category?->name],
            );

            return 'embedded';
        } catch (\Throwable $e) {
            return 'error';
        }
    }

    /**
     * Nächste Nachbarn (ähnliche erledigte Tickets) + deren Kategorie — das stärkste Signal
     * für die Triage. Fehler/kein Provider → leer (Retrieval ist optional).
     *
     * @return array<int, array{title:?string, category:?string, score:float}>
     */
    public function similar(HelpdeskTicket $ticket, string $text, int $limit = 5): array
    {
        if (trim($text) === '') {
            return [];
        }
        try {
            $hits = app(EmbeddingService::class)->search(
                (int) $ticket->team_id, $text,
                [$this->entityType((int) $ticket->helpdesk_board_id)],
                $limit, 0.2,
            );
        } catch (\Throwable $e) {
            return [];
        }

        return collect($hits)
            ->filter(fn ($h) => (string) ($h['entity_id'] ?? '') !== (string) $ticket->id)
            ->map(function ($h) {
                $m = $h['metadata'] ?? [];
                if (! is_array($m)) {
                    $m = json_decode((string) $m, true) ?: [];
                }
                $t = HelpdeskTicket::find($h['entity_id'] ?? 0);

                return [
                    'title' => $t?->title,
                    'category' => $m['category'] ?? $t?->category?->name,
                    'score' => round((float) ($h['score'] ?? 0), 3),
                ];
            })
            ->filter(fn ($n) => $n['category'])
            ->values()->all();
    }
}
