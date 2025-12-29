<?php

namespace Platform\Helpdesk\Models;

use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Enums\TicketEscalationLevel;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\HasTimeAncestors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

use Platform\ActivityLog\Traits\LogsActivity;

class HelpdeskTicket extends Model implements HasDisplayName, HasTimeAncestors
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
        'due_date',
        'status',
        'priority',
        'story_points',

        'is_done',
        'order',
        'slot_order',
        'helpdesk_board_id',
        'helpdesk_board_slot_id',
        'helpdesk_ticket_group_id',
        'comms_channel_id',
        'escalation_level',
        'escalated_at',
        'escalation_count',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'story_points' => TicketStoryPoints::class,
        'escalation_level' => TicketEscalationLevel::class,
        'due_date' => 'date',
        'escalated_at' => 'datetime'
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()->currentTeam->id;
            }
        });

        static::created(function (self $model) {
            // Dispatche Event für AI-Verarbeitung
            event(new \Platform\Helpdesk\Events\TicketCreated($model));
        });
    }

    public function setUserInChargeIdAttribute($value)
    {
        $this->attributes['user_in_charge_id'] = empty($value) || $value === 'null' ? null : (int)$value;
    }

    public function setDueDateAttribute($value)
    {
        $this->attributes['due_date'] = empty($value) || $value === 'null' ? null : $value;
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function helpdeskBoard()
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    public function helpdeskBoardSlot()
    {
        return $this->belongsTo(HelpdeskBoardSlot::class, 'helpdesk_board_slot_id');
    }

    public function helpdeskTicketGroup()
    {
        return $this->belongsTo(HelpdeskTicketGroup::class, 'helpdesk_ticket_group_id');
    }

    public function userInCharge()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }

    public function getSlaAttribute()
    {
        return $this->helpdeskBoard?->sla;
    }

    public function escalations()
    {
        return $this->hasMany(HelpdeskTicketEscalation::class, 'helpdesk_ticket_id');
    }

    public function currentEscalation()
    {
        return $this->escalations()->latest('escalated_at')->first();
    }

    public function isEscalated(): bool
    {
        return $this->escalation_level?->isEscalated() ?? false;
    }

    public function isCritical(): bool
    {
        return $this->escalation_level?->isCritical() ?? false;
    }

    public function aiClassifications()
    {
        return $this->hasMany(HelpdeskAiClassification::class, 'helpdesk_ticket_id');
    }

    public function aiResponses()
    {
        return $this->hasMany(HelpdeskAiResponse::class, 'helpdesk_ticket_id');
    }

    public function resolution()
    {
        return $this->hasOne(HelpdeskTicketResolution::class, 'helpdesk_ticket_id');
    }

    /**
     * Holt AI-Settings vom Board (mit Template-Fallback)
     */
    public function getAiSettings(): ?\Platform\Helpdesk\Models\HelpdeskBoardAiSettings
    {
        if (!$this->helpdeskBoard) {
            return null;
        }

        return HelpdeskBoardAiSettings::getOrCreateForBoard($this->helpdeskBoard);
    }

    /**
     * Gibt alle Vorfahren-Kontexte für die Zeitkaskade zurück.
     * Ticket → Board (als Root)
     */
    public function timeAncestors(): array
    {
        $ancestors = [];

        // Board als Root-Kontext (bei Tickets ist das Board immer der Root)
        if ($this->helpdeskBoard) {
            $ancestors[] = [
                'type' => get_class($this->helpdeskBoard),
                'id' => $this->helpdeskBoard->id,
                'is_root' => true, // Board ist Root-Kontext für Tickets
                'label' => $this->helpdeskBoard->getDisplayName() ?? $this->helpdeskBoard->name ?? 'Unbekanntes Board',
            ];
        }

        return $ancestors;
    }

    /**
     * Gibt den anzeigbaren Namen des Tickets zurück.
     * 
     * @return string|null
     */
    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
