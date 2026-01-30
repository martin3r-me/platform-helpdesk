<?php

namespace Platform\Helpdesk\Contracts;

use Platform\Helpdesk\Models\HelpdeskErrorOccurrence;
use Throwable;

interface ErrorTrackerContract
{
    /**
     * Erfasst einen Fehler und erstellt bei Bedarf ein Ticket
     *
     * @param Throwable $e Die aufgetretene Exception
     * @param array $context Zusätzlicher Kontext (http_code, url, user_id, etc.)
     * @return HelpdeskErrorOccurrence|null Die erstellte oder aktualisierte Occurrence
     */
    public function capture(Throwable $e, array $context = []): ?HelpdeskErrorOccurrence;

    /**
     * Findet alle offenen Occurrences für ein Board
     *
     * @param int $boardId Die Board-ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOpenOccurrences(int $boardId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Findet alle Occurrences für ein Board
     *
     * @param int $boardId Die Board-ID
     * @param string|null $status Status-Filter (open, acknowledged, resolved, ignored)
     * @param int $limit Maximale Anzahl
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOccurrences(
        int $boardId,
        ?string $status = null,
        int $limit = 50
    ): \Illuminate\Database\Eloquent\Collection;
}
