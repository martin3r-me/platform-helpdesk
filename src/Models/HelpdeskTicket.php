<?php

namespace Platform\Helpdesk\Models;

use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Enums\TicketEscalationLevel;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\AgendaRenderable;
use Platform\Integrations\Contracts\SocialMediaAccountLinkableInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Traits\HasExtraFields;
use Platform\Core\Traits\HasContextFileReferences;
use Platform\Core\Traits\TracksLastViewed;
use Platform\Organization\Traits\HasTimeEntries;

class HelpdeskTicket extends Model implements HasDisplayName, SocialMediaAccountLinkableInterface, InheritsExtraFields, AgendaRenderable
{
    use HasFactory, SoftDeletes, LogsActivity, HasExtraFields, HasContextFileReferences, HasTimeEntries, TracksLastViewed;

    protected int $stalenessThresholdDays = 120;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'notes',
        'dod',
        'due_date',
        'priority',
        'story_points',

        'is_done',
        'order',
        'slot_order',
        'helpdesk_board_id',
        'helpdesk_board_slot_id',
        'helpdesk_board_category_id',
        'helpdesk_ticket_group_id',
        'escalation_level',
        'escalated_at',
        'escalation_count',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
        'agent_waiting_at',
        'agent_session_id',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'story_points' => TicketStoryPoints::class,
        'escalation_level' => TicketEscalationLevel::class,
        'due_date' => 'date',
        'done_at' => 'datetime',
        'escalated_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'agent_waiting_at' => 'datetime',
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

        // Zuweisung → Eingang: übergibt jemand anderes das Ticket AN MICH
        // (Ownership-Wechsel, Actor ≠ neuer Inhaber), landet es im Posteingang.
        static::created(function (self $model) {
            self::notifyAssignment($model);
        });
        static::updated(function (self $model) {
            if ($model->wasChanged('user_in_charge_id')) {
                self::notifyAssignment($model);
            }
        });
    }

    /**
     * Push ein „dir zugewiesen"-Ticket in den Eingang des neuen Inhabers — nur bei
     * Fremd-Zuweisung (kein Self-Assign, kein System-Actor). Loose/guarded.
     */
    protected static function notifyAssignment(self $ticket): void
    {
        $assignee = $ticket->user_in_charge_id;
        $actor = Auth::id();

        if (! $assignee || ! $actor || (int) $assignee === (int) $actor) {
            return;
        }
        if (! class_exists(\Platform\Inbox\Inbox::class)) {
            return;
        }

        try {
            $assigner = Auth::user();
            \Platform\Inbox\Inbox::deliver([
                'user_id'           => (int) $assignee,
                'team_id'           => (int) $ticket->team_id,
                'channel'           => 'ticket',
                'subject'           => $ticket->title ?: 'Ticket',
                'body'              => $ticket->description ?? null,
                'source'            => $ticket,
                'sender_kind'       => 'user',
                'sender_label'      => $assigner?->fullname ?? $assigner?->name ?? 'Jemand',
                'sender_identifier' => (string) $actor,
            ]);
        } catch (\Throwable $e) {
            // Zuweisung darf nie an der Inbox scheitern.
        }
    }

    public function setUserInChargeIdAttribute($value)
    {
        $this->attributes['user_in_charge_id'] = empty($value) || $value === 'null' ? null : (int)$value;
    }

    public function setDueDateAttribute($value)
    {
        if (empty($value) || $value === 'null') {
            $this->attributes['due_date'] = null;
        } else {
            $this->attributes['due_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
        }
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

    public function category()
    {
        return $this->belongsTo(HelpdeskBoardCategory::class, 'helpdesk_board_category_id');
    }

    /**
     * Scope: Nur Tickets, die der User sehen darf =
     * - Ersteller (user_id), ODER
     * - Zuständiger (user_in_charge_id), ODER
     * - das zugehörige Board ist graph-erreichbar (Tickets erben die Verortung
     *   ihres Boards; einzeln werden Tickets nicht aufgehängt).
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $reachable = app(\Platform\Core\Authz\AuthzResolver::class)->reachableEntityIds($user, 'read');
        $reachableBoardIds = empty($reachable) ? [] : \Illuminate\Support\Facades\DB::table('authz_resource_link')
            ->where('resource_type', \Platform\Helpdesk\Models\HelpdeskBoard::class)
            ->whereIn('scope_id', $reachable)
            ->pluck('resource_id')
            ->all();

        return $query->where(function ($q) use ($user, $reachableBoardIds) {
            $q->where('user_id', $user->id)
              ->orWhere('user_in_charge_id', $user->id)
              ->orWhereIn('helpdesk_board_id', $reachableBoardIds);
        });
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

    /**
     * Tickets erben Extra-Felder vom zugeordneten Helpdesk-Board.
     */
    public function extraFieldParents(): array
    {
        return array_filter([$this->helpdeskBoard]);
    }

    // ── AgendaRenderable ──────────────────────────────────────

    public function toAgendaItem(): array
    {
        return [
            'title' => $this->title,
            'description' => null,
            'icon' => '🎫',
            'color' => null,
            'status' => $this->is_done ? 'Erledigt' : 'Offen',
            'status_color' => $this->is_done ? 'green' : 'orange',
            'url' => route('helpdesk.tickets.show', $this),
            'meta' => [
                'priority' => $this->priority?->value,
                'escalation_level' => $this->escalation_level?->value,
            ],
        ];
    }
}
