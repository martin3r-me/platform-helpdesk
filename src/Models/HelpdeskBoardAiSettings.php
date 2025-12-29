<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskBoardAiSettings extends Model
{
    protected $fillable = [
        'helpdesk_board_id',
        'team_id',
        'auto_response_enabled',
        'auto_response_timing_minutes',
        'auto_response_immediate_enabled',
        'auto_response_confidence_threshold',
        'auto_assignment_enabled',
        'auto_assignment_confidence_threshold',
        'ai_model',
        'human_in_loop_enabled',
        'human_in_loop_threshold',
        'ai_enabled_for_escalated',
        'knowledge_base_categories',
        'template_id',
    ];

    protected $casts = [
        'auto_response_enabled' => 'boolean',
        'auto_response_immediate_enabled' => 'boolean',
        'auto_response_confidence_threshold' => 'decimal:2',
        'auto_assignment_enabled' => 'boolean',
        'auto_assignment_confidence_threshold' => 'decimal:2',
        'human_in_loop_enabled' => 'boolean',
        'human_in_loop_threshold' => 'decimal:2',
        'ai_enabled_for_escalated' => 'boolean',
        'knowledge_base_categories' => 'array',
    ];

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Gibt die Settings zurück oder erstellt Default-Settings
     */
    public static function getOrCreateForBoard(HelpdeskBoard $board): self
    {
        return static::firstOrCreate(
            ['helpdesk_board_id' => $board->id],
            [
                'team_id' => $board->team_id,
                'auto_response_enabled' => false,
                'auto_response_timing_minutes' => 30,
                'auto_response_immediate_enabled' => true,
                'auto_response_confidence_threshold' => 0.90,
                'auto_assignment_enabled' => false,
                'auto_assignment_confidence_threshold' => 0.70,
                'ai_model' => 'gpt-4o-mini',
                'human_in_loop_enabled' => true,
                'human_in_loop_threshold' => 0.90,
                'ai_enabled_for_escalated' => false,
                'knowledge_base_categories' => null,
            ]
        );
    }
}

