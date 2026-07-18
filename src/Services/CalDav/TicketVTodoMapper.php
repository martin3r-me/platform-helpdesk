<?php

namespace Platform\Helpdesk\Services\CalDav;

use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Sabre\VObject\Component\VCalendar;

/**
 * Bildet ein {@see HelpdeskTicket} auf ein iCalendar-VTODO ab (read-only CalDAV).
 *
 * VTODO -> Apple *Erinnerungen* zeigt die Kalender als abhakbare Listen. Rein,
 * isoliert testbar. Siehe modules/planner/docs/caldav.md (gleiches Muster).
 */
class TicketVTodoMapper
{
    /** TicketPriority → iCal-PRIORITY (1 = höchste … 9 = niedrigste). */
    private const PRIORITY_MAP = [
        'high'   => 1,
        'normal' => 5,
        'low'    => 9,
    ];

    public function toVCalendar(HelpdeskTicket $ticket): VCalendar
    {
        $cal = new VCalendar([
            'PRODID' => '-//Platform Helpdesk//CalDAV//DE',
            'VTODO' => [
                'UID' => $ticket->uuid,
                'SUMMARY' => (string) ($ticket->title ?? 'Ticket '.$ticket->getKey()),
            ],
        ]);

        $vtodo = $cal->VTODO;

        if ($ticket->due_date) {
            $vtodo->add('DUE', $ticket->due_date);
        }

        $priority = $this->priority($ticket->priority);
        if ($priority !== null) {
            $vtodo->add('PRIORITY', $priority);
        }

        if ($ticket->is_done) {
            $vtodo->add('STATUS', 'COMPLETED');
            $vtodo->add('PERCENT-COMPLETE', 100);
        } else {
            $vtodo->add('STATUS', 'NEEDS-ACTION');
        }

        if ($ticket->updated_at) {
            $vtodo->add('LAST-MODIFIED', $ticket->updated_at->format('Ymd\THis\Z'));
        }

        return $cal;
    }

    public function serialize(HelpdeskTicket $ticket): string
    {
        return $this->toVCalendar($ticket)->serialize();
    }

    public static function etagFor(HelpdeskTicket $ticket): string
    {
        return '"' . md5(($ticket->updated_at?->getTimestamp() ?? 0) . ':' . $ticket->getKey()) . '"';
    }

    private function priority(?TicketPriority $priority): ?int
    {
        if ($priority === null) {
            return null;
        }

        return self::PRIORITY_MAP[$priority->value] ?? null;
    }
}
