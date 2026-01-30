<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class HelpdeskErrorOccurrence extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'helpdesk_board_id',
        'helpdesk_ticket_id',
        'team_id',
        'error_hash',
        'exception_class',
        'message',
        'file',
        'line',
        'http_code',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'sample_data',
        'status',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'http_code' => 'integer',
        'line' => 'integer',
        'occurrence_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'sample_data' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'resolved_by_user_id');
    }

    /**
     * Generiert einen eindeutigen Hash für Deduplizierung
     */
    public static function generateHash(Throwable $e, ?int $httpCode = null): string
    {
        $components = [
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            $httpCode ?? 0,
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Generiert einen Hash aus den Komponenten
     */
    public static function generateHashFromComponents(
        string $exceptionClass,
        ?string $file,
        ?int $line,
        ?int $httpCode = null
    ): string {
        $components = [
            $exceptionClass,
            $file ?? '',
            $line ?? 0,
            $httpCode ?? 0,
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Inkrementiert den Counter und aktualisiert Sample-Daten
     */
    public function recordOccurrence(array $sampleData = []): self
    {
        $this->occurrence_count++;
        $this->last_seen_at = now();

        if (!empty($sampleData)) {
            $this->sample_data = $sampleData;
        }

        $this->save();

        return $this;
    }

    /**
     * Markiert die Occurrence als resolved
     */
    public function resolve(?int $userId = null): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_by_user_id = $userId;
        $this->resolved_at = now();
        $this->save();

        return $this;
    }

    /**
     * Markiert die Occurrence als acknowledged
     */
    public function acknowledge(): self
    {
        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->save();

        return $this;
    }

    /**
     * Markiert die Occurrence als ignored
     */
    public function ignore(): self
    {
        $this->status = self::STATUS_IGNORED;
        $this->save();

        return $this;
    }

    /**
     * Findet eine existierende Occurrence im Dedupe-Fenster
     */
    public static function findExistingInDedupeWindow(
        int $boardId,
        string $hash,
        int $dedupeWindowHours
    ): ?self {
        return static::where('helpdesk_board_id', $boardId)
            ->where('error_hash', $hash)
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED])
            ->where('last_seen_at', '>=', now()->subHours($dedupeWindowHours))
            ->first();
    }

    /**
     * Prüft ob die Occurrence offen ist
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Prüft ob die Occurrence resolved ist
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Formatiert die Exception-Info für die Anzeige
     */
    public function getFormattedLocation(): string
    {
        if ($this->file && $this->line) {
            return "{$this->file}:{$this->line}";
        }

        return $this->file ?? 'Unknown location';
    }

    /**
     * Gibt einen kurzen Bezeichner für die Exception zurück
     */
    public function getShortExceptionClass(): string
    {
        if (!$this->exception_class) {
            return 'Unknown';
        }

        $parts = explode('\\', $this->exception_class);

        return end($parts);
    }
}
