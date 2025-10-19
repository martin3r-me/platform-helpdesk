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
        'team_id',
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
        
        static::addGlobalScope('team', function ($builder) {
            if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->currentTeam) {
                $builder->where('team_id', \Illuminate\Support\Facades\Auth::user()->currentTeam->id);
            }
        });

        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            if (! $model->team_id && \Illuminate\Support\Facades\Auth::check()) {
                $model->team_id = \Illuminate\Support\Facades\Auth::user()->currentTeam->id ?? null;
            }
        });
    }

    public function helpdeskBoards(): HasMany
    {
        return $this->hasMany(HelpdeskBoard::class, 'helpdesk_board_sla_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
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
     * Bestimmt das Eskalations-Level basierend auf SLA-Zeit
     */
    public function getEscalationLevel($ticket): \Platform\Helpdesk\Enums\TicketEscalationLevel
    {
        if (!$this->is_active) {
            return \Platform\Helpdesk\Enums\TicketEscalationLevel::NONE;
        }

        $totalTime = $this->response_time_hours ?? $this->resolution_time_hours;
        
        if ($totalTime === null) {
            return \Platform\Helpdesk\Enums\TicketEscalationLevel::NONE;
        }
        
        $elapsedTime = $ticket->created_at->diffInHours(now());
        $elapsedPercentage = ($elapsedTime / $totalTime) * 100;
        
        return match(true) {
            $elapsedPercentage >= 300 => \Platform\Helpdesk\Enums\TicketEscalationLevel::URGENT,
            $elapsedPercentage >= 200 => \Platform\Helpdesk\Enums\TicketEscalationLevel::CRITICAL,
            $elapsedPercentage >= 100 => \Platform\Helpdesk\Enums\TicketEscalationLevel::ESCALATED,
            $elapsedPercentage >= 80  => \Platform\Helpdesk\Enums\TicketEscalationLevel::WARNING,
            default => \Platform\Helpdesk\Enums\TicketEscalationLevel::NONE,
        };
    }

    /**
     * Prüft ob eine Eskalation nötig ist
     */
    public function needsEscalation($ticket): bool
    {
        return $this->getEscalationLevel($ticket)->isEscalated();
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
            return $this->response_time_hours - $hoursSinceCreation;
        }

        // Prüfe Lösungszeit (nur wenn Ticket nicht erledigt ist)
        if (!$ticket->is_done && $this->resolution_time_hours) {
            return $this->resolution_time_hours - $hoursSinceCreation;
        }

        return null;
    }
}
