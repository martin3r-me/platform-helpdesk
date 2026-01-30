<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskBoardErrorSettings extends Model
{
    public const DEFAULT_CAPTURE_CODES = [400, 401, 403, 404, 500, 502, 503, 504];

    public const DEFAULT_PRIORITY_MAPPING = [
        '400' => 'low',
        '401' => 'medium',
        '403' => 'medium',
        '404' => 'low',
        '500' => 'high',
        '502' => 'high',
        '503' => 'high',
        '504' => 'medium',
    ];

    protected $fillable = [
        'helpdesk_board_id',
        'team_id',
        'enabled',
        'capture_codes',
        'priority_mapping',
        'dedupe_window_hours',
        'auto_create_ticket',
        'include_stack_trace',
        'stack_trace_limit',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'capture_codes' => 'array',
        'priority_mapping' => 'array',
        'dedupe_window_hours' => 'integer',
        'auto_create_ticket' => 'boolean',
        'include_stack_trace' => 'boolean',
        'stack_trace_limit' => 'integer',
    ];

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function errorOccurrences(): HasMany
    {
        return $this->hasMany(HelpdeskErrorOccurrence::class, 'helpdesk_board_id', 'helpdesk_board_id');
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
                'enabled' => false,
                'capture_codes' => self::DEFAULT_CAPTURE_CODES,
                'priority_mapping' => self::DEFAULT_PRIORITY_MAPPING,
                'dedupe_window_hours' => 24,
                'auto_create_ticket' => true,
                'include_stack_trace' => true,
                'stack_trace_limit' => 50,
            ]
        );
    }

    /**
     * Prüft ob ein HTTP-Code erfasst werden soll
     */
    public function shouldCaptureCode(?int $code): bool
    {
        if ($code === null) {
            return true; // Capture exceptions without HTTP code
        }

        $codes = $this->capture_codes ?? self::DEFAULT_CAPTURE_CODES;

        return in_array($code, $codes, true);
    }

    /**
     * Gibt die Priority für einen HTTP-Code zurück
     */
    public function getPriorityForCode(?int $code): string
    {
        if ($code === null) {
            return 'high'; // Default for exceptions without HTTP code
        }

        $mapping = $this->priority_mapping ?? self::DEFAULT_PRIORITY_MAPPING;

        return $mapping[(string) $code] ?? 'medium';
    }

    /**
     * Gibt die Capture-Codes als Array zurück
     */
    public function getCaptureCodes(): array
    {
        return $this->capture_codes ?? self::DEFAULT_CAPTURE_CODES;
    }

    /**
     * Gibt das Priority-Mapping als Array zurück
     */
    public function getPriorityMapping(): array
    {
        return $this->priority_mapping ?? self::DEFAULT_PRIORITY_MAPPING;
    }
}
