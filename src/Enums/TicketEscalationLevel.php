<?php

namespace Platform\Helpdesk\Enums;

enum TicketEscalationLevel: string
{
    case NONE = 'none';
    case WARNING = 'warning';
    case ESCALATED = 'escalated';
    case CRITICAL = 'critical';
    case URGENT = 'urgent';

    public function description(): string
    {
        return match($this) {
            self::NONE => 'Keine Eskalation',
            self::WARNING => 'Warnung (80% SLA-Zeit)',
            self::ESCALATED => 'Eskaliert (100% SLA-Zeit)',
            self::CRITICAL => 'Kritisch (200% SLA-Zeit)',
            self::URGENT => 'Dringend (300% SLA-Zeit)',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NONE => 'gray',
            self::WARNING => 'yellow',
            self::ESCALATED => 'orange',
            self::CRITICAL => 'red',
            self::URGENT => 'purple',
        };
    }

    public function isEscalated(): bool
    {
        return $this !== self::NONE;
    }

    public function isCritical(): bool
    {
        return in_array($this, [self::CRITICAL, self::URGENT]);
    }
}
