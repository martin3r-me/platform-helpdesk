<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskAiClassification extends Model
{
    protected $fillable = [
        'helpdesk_ticket_id',
        'category',
        'priority_prediction',
        'assignee_suggestion_user_id',
        'confidence_score',
        'ai_model_used',
        'raw_response',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function assigneeSuggestion(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'assignee_suggestion_user_id');
    }
}

