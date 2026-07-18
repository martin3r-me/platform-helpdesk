<?php

namespace Platform\Helpdesk\Dav;

use Platform\Core\Contracts\DavModuleInterface;
use Platform\Core\Dav\DavContext;
use Platform\Helpdesk\Services\CalDav\TicketVTodoMapper;

/**
 * Stellt die Helpdesk-Tickets als CalDAV-Kalender (VTODO) an der Core-DAV-
 * Infrastruktur bereit. Erscheinen zusammen mit Planner-Aufgaben in einem
 * CalDAV-Account. Siehe modules/planner/docs/caldav.md.
 */
class HelpdeskCalDavModule implements DavModuleInterface
{
    public function key(): string
    {
        return 'helpdesk';
    }

    public function type(): string
    {
        return 'caldav';
    }

    public function backend(DavContext $context): object
    {
        return new HelpdeskCalDavBackend($context, new TicketVTodoMapper());
    }
}
