<?php

namespace Platform\Helpdesk\Models;

use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Enums\TicketEscalationLevel;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Integrations\Contracts\SocialMediaAccountLinkableInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Traits\HasExtraFields;

class HelpdeskTicket extends Model implements HasDisplayName, HasTimeAncestors, SocialMediaAccountLinkableInterface
{
    use HasFactory, SoftDeletes, LogsActivity, HasExtraFields;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'notes',
        'dod',
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
        'escalation_level',
        'escalated_at',
        'escalation_count',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'story_points' => TicketStoryPoints::class,
        'escalation_level' => TicketEscalationLevel::class,
        'due_date' => 'date',
        'escalated_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'dod' => 'array',
    ];

    /**
     * Alias für Abwärtskompatibilität: description -> notes
     */
    public function getDescriptionAttribute()
    {
        return $this->notes;
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['notes'] = $value;
    }

    /**
     * Berechnet den Fortschritt der DoD (Definition of Done)
     * @return array ['completed' => int, 'total' => int, 'percentage' => int]
     */
    public function getDodProgressAttribute(): array
    {
        $dod = $this->dod ?? [];
        $total = count($dod);

        if ($total === 0) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0];
        }

        $completed = collect($dod)->filter(fn($item) => $item['checked'] ?? false)->count();
        $percentage = (int) round(($completed / $total) * 100);

        return ['completed' => $completed, 'total' => $total, 'percentage' => $percentage];
    }

    /**
     * Prüft ob alle DoD-Punkte abgehakt sind
     */
    public function isDodComplete(): bool
    {
        $progress = $this->dod_progress;
        return $progress['total'] > 0 && $progress['completed'] === $progress['total'];
    }

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

    public function lockedByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'locked_by_user_id');
    }

    /**
     * Prüft ob das Ticket gesperrt ist
     */
    public function isLocked(): bool
    {
        return $this->is_locked === true;
    }

    /**
     * Sperrt das Ticket
     */
    public function lock(): void
    {
        $this->is_locked = true;
        $this->locked_at = now();
        $this->locked_by_user_id = Auth::id();
        $this->save();
    }

    /**
     * Entsperrt das Ticket
     */
    public function unlock(): void
    {
        $this->is_locked = false;
        $this->locked_at = null;
        $this->locked_by_user_id = null;
        $this->save();
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

    /**
     * GitHub Repositories dieses Tickets (über lose Verknüpfung)
     */
    public function githubRepositories()
    {
        $service = app(\Platform\Integrations\Services\IntegrationAccountLinkService::class);
        return $service->getLinkedGithubRepositories($this);
    }

    /**
     * SocialMediaAccountLinkableInterface Implementation
     */
    public function getSocialMediaAccountLinkableId(): int
    {
        return $this->id;
    }

    public function getSocialMediaAccountLinkableType(): string
    {
        return self::class;
    }

    public function getTeamId(): int
    {
        return $this->team_id ?? 0;
    }
}
