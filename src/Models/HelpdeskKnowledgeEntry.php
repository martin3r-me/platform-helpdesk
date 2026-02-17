<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HelpdeskKnowledgeEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'helpdesk_board_id',
        'team_id',
        'title',
        'problem',
        'solution',
        'tags',
        'source_ticket_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            // team_id vom Board übernehmen
            if (!$model->team_id && $model->helpdesk_board_id) {
                $board = HelpdeskBoard::find($model->helpdesk_board_id);
                if ($board) {
                    $model->team_id = $board->team_id;
                }
            }
        });
    }

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'source_ticket_id');
    }
}
