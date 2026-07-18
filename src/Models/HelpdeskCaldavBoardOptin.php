<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in eines Users für ein Board im CalDAV-Account (default: nicht vorhanden).
 * Ein Datensatz = Board wird als eigene Ticket-Liste in Apple Erinnerungen gezeigt.
 *
 * Siehe modules/planner/docs/caldav.md.
 */
class HelpdeskCaldavBoardOptin extends Model
{
    protected $table = 'helpdesk_caldav_board_optins';

    protected $fillable = [
        'user_id',
        'helpdesk_board_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }
}
