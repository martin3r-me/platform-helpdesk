<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskBoardSnapshotPerson extends Model
{
    protected $table = 'helpdesk_board_snapshot_people';

    protected $fillable = [
        'snapshot_id',
        'user_id',
        'user_name',
        'open_tickets',
        'done_tickets',
        'overdue_tickets',
        'escalated_tickets',
        'sp_open',
        'sp_done',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoardSnapshot::class, 'snapshot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }
}
