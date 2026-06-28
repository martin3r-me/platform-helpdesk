<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskBoardSnapshotSlot extends Model
{
    protected $table = 'helpdesk_board_snapshot_slots';

    protected $fillable = [
        'snapshot_id',
        'slot_id',
        'slot_name',
        'slot_order',
        'open_tickets',
        'done_tickets',
        'total_tickets',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoardSnapshot::class, 'snapshot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoardSlot::class, 'slot_id');
    }
}
