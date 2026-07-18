<?php

namespace Platform\Helpdesk\Dav;

use Illuminate\Database\Eloquent\Builder;
use Platform\Core\Dav\DavContext;
use Platform\Core\Models\DavSubscription;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskCaldavBoardOptin;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Services\CalDav\TicketVTodoMapper;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Reader;

/**
 * Read-only CalDAV-Backend für Helpdesk-Tickets (VTODO).
 *
 * Ein Account zeigt „Meine Tickets" (immer) + je opt-in-Board eine Liste
 * (HelpdeskCaldavBoardOptin). Läuft über die Core-DAV-Composite, d. h. dieselben
 * Kalender erscheinen zusammen mit Planner-Aufgaben in EINEM CalDAV-Account.
 * Schreib-Ops werfen {@see Forbidden}. Siehe modules/planner/docs/caldav.md.
 */
class HelpdeskCalDavBackend extends AbstractBackend implements SyncSupport
{
    private const MINE = 'mine';

    public function __construct(
        private readonly DavContext $context,
        private readonly TicketVTodoMapper $mapper,
    ) {
    }

    private function sub(): DavSubscription
    {
        return $this->context->subscription();
    }

    private function userId(): int
    {
        return (int) $this->sub()->user_id;
    }

    // ----------------------------------------------------------------
    // Kalender
    // ----------------------------------------------------------------

    public function getCalendarsForUser($principalUri)
    {
        if ($this->sub()->type !== 'caldav'
            || $principalUri !== 'principals/'.$this->userId()) {
            return [];
        }

        $calendars = [$this->calendarArray(self::MINE, 'meine-tickets', 'Meine Tickets')];

        foreach ($this->optedInBoards() as $board) {
            $calendars[] = $this->calendarArray(
                (int) $board->id,
                'board-'.$board->id,
                $board->name ?? ('Board '.$board->id),
            );
        }

        return $calendars;
    }

    /**
     * @param  string|int  $id
     * @return array<string, mixed>
     */
    private function calendarArray($id, string $uri, string $displayName): array
    {
        return [
            'id' => $id,
            'uri' => $uri,
            'principaluri' => 'principals/'.$this->userId(),
            '{DAV:}displayname' => $displayName,
            '{'.CalDavPlugin::NS_CALENDARSERVER.'}getctag' => $this->computeCtag($id),
            '{http://sabredav.org/ns}sync-token' => $this->computeCtag($id),
            '{'.CalDavPlugin::NS_CALDAV.'}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
        ];
    }

    /**
     * WebDAV-Sync (nötig für Apple Erinnerungen). Ohne Change-Log: bei jeder
     * Änderung Voll-Resync (Token veraltet -> null), sonst Delta leer.
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null)
    {
        $this->assertAllowedCalendar($calendarId);

        $current = $this->computeCtag($calendarId);

        if (! empty($syncToken) && $syncToken === $current) {
            return ['syncToken' => $current, 'added' => [], 'modified' => [], 'deleted' => []];
        }

        if (! empty($syncToken)) {
            return null;
        }

        $uris = $this->ticketsQuery($calendarId)
            ->pluck('uuid')
            ->map(fn ($uuid) => $uuid.'.ics')
            ->all();

        return ['syncToken' => $current, 'added' => $uris, 'modified' => [], 'deleted' => []];
    }

    public function updateCalendar($calendarId, PropPatch $propPatch)
    {
        // Read-only.
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new Forbidden('Der Ticket-Kalender ist schreibgeschützt.');
    }

    public function deleteCalendar($calendarId)
    {
        throw new Forbidden('Der Ticket-Kalender ist schreibgeschützt.');
    }

    // ----------------------------------------------------------------
    // Objekte (VTODO)
    // ----------------------------------------------------------------

    public function getCalendarObjects($calendarId)
    {
        $this->assertAllowedCalendar($calendarId);

        return $this->ticketsQuery($calendarId)
            ->get(['id', 'uuid', 'updated_at'])
            ->map(fn (HelpdeskTicket $ticket) => [
                'id' => $ticket->id,
                'uri' => $ticket->uuid.'.ics',
                'calendarid' => $calendarId,
                'etag' => TicketVTodoMapper::etagFor($ticket),
                'lastmodified' => $ticket->updated_at?->getTimestamp() ?? 0,
                'component' => 'vtodo',
            ])
            ->all();
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        $this->assertAllowedCalendar($calendarId);

        $ticket = $this->ticketsQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if (! $ticket) {
            return null;
        }

        $data = $this->mapper->serialize($ticket);

        return [
            'id' => $ticket->id,
            'uri' => $objectUri,
            'calendarid' => $calendarId,
            'etag' => TicketVTodoMapper::etagFor($ticket),
            'lastmodified' => $ticket->updated_at?->getTimestamp() ?? 0,
            'size' => strlen($data),
            'calendardata' => $data,
            'component' => 'vtodo',
        ];
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $this->assertAllowedCalendar($calendarId);

        $vtodo = $this->readVtodo($calendarData);

        $ticket = new HelpdeskTicket();
        $ticket->uuid = $this->uuidFromUri($objectUri);
        $ticket->user_id = $this->userId();
        $ticket->team_id = $this->sub()->team_id;
        $ticket->title = $this->summary($vtodo) ?: 'Ticket';
        $ticket->due_date = $this->due($vtodo);
        $ticket->is_done = $this->isCompleted($vtodo);
        if ($calendarId !== self::MINE) {
            $ticket->helpdesk_board_id = (int) $calendarId;
        }
        $ticket->save();

        return TicketVTodoMapper::etagFor($ticket->refresh());
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $this->assertAllowedCalendar($calendarId);

        $ticket = $this->ticketsQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if (! $ticket) {
            throw new NotFound('Ticket nicht gefunden.');
        }

        $vtodo = $this->readVtodo($calendarData);

        // Titel NICHT zurückschreiben (SUMMARY enthält Board-Präfix). Nur
        // Fälligkeit + Status.
        if (($due = $this->due($vtodo)) !== null) {
            $ticket->due_date = $due;
        }
        $ticket->is_done = $this->isCompleted($vtodo);
        $ticket->save();

        return TicketVTodoMapper::etagFor($ticket->refresh());
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $this->assertAllowedCalendar($calendarId);

        $ticket = $this->ticketsQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if ($ticket) {
            $ticket->delete();
        }
    }

    // ----------------------------------------------------------------
    // VTODO-Parsing (Write-Back)
    // ----------------------------------------------------------------

    private function readVtodo(string $calendarData): ?VTodo
    {
        $vcal = Reader::read($calendarData);

        return $vcal->VTODO ?? null;
    }

    private function summary(?VTodo $vtodo): string
    {
        return $vtodo && $vtodo->SUMMARY ? trim((string) $vtodo->SUMMARY) : '';
    }

    private function due(?VTodo $vtodo): ?\DateTimeInterface
    {
        if ($vtodo && $vtodo->DUE) {
            return $vtodo->DUE->getDateTime();
        }

        return null;
    }

    private function isCompleted(?VTodo $vtodo): bool
    {
        if (! $vtodo) {
            return false;
        }

        if ($vtodo->STATUS && strtoupper(trim((string) $vtodo->STATUS)) === 'COMPLETED') {
            return true;
        }

        return $vtodo->{'PERCENT-COMPLETE'} && (int) (string) $vtodo->{'PERCENT-COMPLETE'} >= 100;
    }

    // ----------------------------------------------------------------
    // Scoping
    // ----------------------------------------------------------------

    /**
     * Boards, die der User fürs CalDAV freigeschaltet hat (default aus), im Team des Abos.
     *
     * @return \Illuminate\Support\Collection<int, HelpdeskBoard>
     */
    private function optedInBoards()
    {
        $ids = HelpdeskCaldavBoardOptin::query()
            ->where('user_id', $this->userId())
            ->pluck('helpdesk_board_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return HelpdeskBoard::query()
            ->whereIn('id', $ids)
            ->where('team_id', $this->sub()->team_id)
            ->get();
    }

    /**
     * @param  string|int  $calendarId
     */
    private function ticketsQuery($calendarId): Builder
    {
        // Immer nur die eigenen Tickets des Abonnenten — auch in Board-Listen.
        $query = HelpdeskTicket::query()
            ->where('team_id', $this->sub()->team_id)
            ->where('user_id', $this->userId());

        if ($calendarId === self::MINE) {
            return $query;
        }

        return $query->where('helpdesk_board_id', (int) $calendarId);
    }

    /**
     * @param  string|int  $calendarId
     */
    private function assertAllowedCalendar($calendarId): void
    {
        if ($calendarId === self::MINE) {
            return;
        }

        $allowed = HelpdeskCaldavBoardOptin::query()
            ->where('user_id', $this->userId())
            ->where('helpdesk_board_id', (int) $calendarId)
            ->exists();

        if (! $allowed) {
            throw new NotFound('Kalender nicht gefunden.');
        }
    }

    /**
     * @param  string|int  $calendarId
     */
    private function computeCtag($calendarId): string
    {
        $agg = $this->ticketsQuery($calendarId)
            ->selectRaw('COUNT(*) as cnt, MAX(updated_at) as maxu')
            ->first();

        $max = $agg?->maxu ? strtotime((string) $agg->maxu) : 0;

        return $max.'-'.($agg?->cnt ?? 0);
    }

    private function uuidFromUri(string $objectUri): string
    {
        return preg_replace('/\.ics$/i', '', $objectUri);
    }
}
