<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;

class HelpdeskKnowledgeBase extends Model
{
    use Encryptable;

    protected $fillable = [
        'team_id',
        'helpdesk_board_id',
        'title',
        'content',
        'category',
        'tags',
        'success_rate',
        'usage_count',
        'language',
        'created_by_user_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'success_rate' => 'decimal:2',
        'usage_count' => 'integer',
    ];

    protected $encryptable = [
        'content' => 'string',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }
}

