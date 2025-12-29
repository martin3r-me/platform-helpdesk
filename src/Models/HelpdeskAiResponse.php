<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;

class HelpdeskAiResponse extends Model
{
    use Encryptable;

    protected $fillable = [
        'helpdesk_ticket_id',
        'comms_channel_id',
        'response_type',
        'response_text',
        'confidence_score',
        'sent_at',
        'user_feedback',
        'ai_model_used',
        'knowledge_base_entry_id',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    protected $encryptable = [
        'response_text' => 'string',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function knowledgeBaseEntry(): BelongsTo
    {
        return $this->belongsTo(HelpdeskKnowledgeBase::class, 'knowledge_base_entry_id');
    }
}

