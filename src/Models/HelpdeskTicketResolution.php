<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;

class HelpdeskTicketResolution extends Model
{
    use Encryptable;

    protected $fillable = [
        'helpdesk_ticket_id',
        'resolution_text',
        'ai_generated',
        'user_confirmed',
        'effectiveness_score',
        'knowledge_base_entry_id',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
        'user_confirmed' => 'boolean',
        'effectiveness_score' => 'decimal:2',
    ];

    protected $encryptable = [
        'resolution_text' => 'string',
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

