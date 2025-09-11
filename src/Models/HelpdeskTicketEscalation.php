<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Helpdesk\Enums\TicketEscalationLevel;

class HelpdeskTicketEscalation extends Model
{
    protected $fillable = [
        'helpdesk_ticket_id',
        'escalation_level',
        'reason',
        'escalated_by_user_id',
        'resolved_by_user_id',
        'escalated_at',
        'resolved_at',
        'notification_sent',
        'notes',
    ];

    protected $casts = [
        'escalation_level' => TicketEscalationLevel::class,
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notification_sent' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'escalated_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'resolved_by_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function resolve($userId = null): void
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by_user_id' => $userId,
        ]);
    }
}
