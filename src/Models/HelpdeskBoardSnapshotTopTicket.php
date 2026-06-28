<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskBoardSnapshotTopTicket extends Model
{
    protected $table = 'helpdesk_board_snapshot_top_tickets';

    protected $fillable = [
        'snapshot_id',
        'ticket_id',
        'ticket_uuid',
        'ticket_title',
        'due_date',
        'ticket_created_at',
        'is_overdue',
        'postpone_count',
        'priority',
        'escalation_level',
        'escalation_count',
        'story_points',
        'user_in_charge_id',
        'user_in_charge_name',
        'rank',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'ticket_created_at' => 'datetime',
        'is_overdue' => 'boolean',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoardSnapshot::class, 'snapshot_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function userInCharge(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }
}
