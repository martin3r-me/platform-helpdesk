<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;

class HelpdeskBoardSla extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'is_active',
        'response_time_hours',
        'resolution_time_hours',
        'order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_active' => 'boolean',
        'response_time_hours' => 'integer',
        'resolution_time_hours' => 'integer',
    ];

    protected static function booted(): void
    {
        Log::info('HelpdeskBoardSla Model: booted() called!');
        
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function helpdeskBoards(): HasMany
    {
        return $this->hasMany(HelpdeskBoard::class, 'helpdesk_board_sla_id');
    }

    /**
     * Prüft, ob ein Ticket die SLA-Zeit überschritten hat
     */
    public function isOverdue($ticket): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        $createdAt = $ticket->created_at;
        $hoursSinceCreation = $createdAt->diffInHours($now);

        // Prüfe Reaktionszeit
        if ($this->response_time_hours && $hoursSinceCreation > $this->response_time_hours) {
            return true;
        }

        // Prüfe Lösungszeit (nur wenn Ticket nicht erledigt ist)
        if (!$ticket->is_done && $this->resolution_time_hours && $hoursSinceCreation > $this->resolution_time_hours) {
            return true;
        }

        return false;
    }

    /**
     * Gibt die verbleibende Zeit bis zur SLA-Überschreitung zurück
     */
    public function getRemainingTime($ticket): ?int
    {
        if (!$this->is_active) {
            return null;
        }

        $now = now();
        $createdAt = $ticket->created_at;
        $hoursSinceCreation = $createdAt->diffInHours($now);

        // Prüfe Reaktionszeit
        if ($this->response_time_hours) {
            $remaining = $this->response_time_hours - $hoursSinceCreation;
            if ($remaining > 0) {
                return $remaining;
            }
        }

        // Prüfe Lösungszeit (nur wenn Ticket nicht erledigt ist)
        if (!$ticket->is_done && $this->resolution_time_hours) {
            $remaining = $this->resolution_time_hours - $hoursSinceCreation;
            if ($remaining > 0) {
                return $remaining;
            }
        }

        return null;
    }
}
