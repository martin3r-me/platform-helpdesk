<?php

namespace Platform\Helpdesk\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Bearbeitung',
            self::Waiting => 'Wartend',
            self::Resolved => 'Gelöst',
            self::Closed => 'Geschlossen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::InProgress => 'blue',
            self::Waiting => 'yellow',
            self::Resolved => 'green',
            self::Closed => 'gray',
        };
    }


}
