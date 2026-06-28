<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Health\Traits\HasHealthSnapshotData;
use Symfony\Component\Uid\UuidV7;

class HelpdeskBoardSnapshot extends Model
{
    use HasHealthSnapshotData;

    protected $table = 'helpdesk_board_snapshots';

    protected $fillable = [
        'helpdesk_board_id',
        // Tickets
        'tickets_total', 'tickets_open', 'tickets_done',
        'tickets_overdue', 'tickets_with_due_date',
        // Escalation
        'tickets_escalated', 'tickets_critical', 'escalations_total_lifetime',
        // Story Points
        'story_points_total', 'story_points_open', 'story_points_done',
        // SLA
        'has_sla', 'sla_response_hours', 'sla_resolution_hours',
        'tickets_breaching_resolution',
        // Workload
        'active_users_count', 'unassigned_tickets',
    ];

    protected $casts = [
        'has_sla' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_snapshot_id');
    }

    public function topTickets(): HasMany
    {
        return $this->hasMany(HelpdeskBoardSnapshotTopTicket::class, 'snapshot_id')
            ->orderBy('rank');
    }

    public function people(): HasMany
    {
        return $this->hasMany(HelpdeskBoardSnapshotPerson::class, 'snapshot_id')
            ->orderByDesc('open_tickets');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(HelpdeskBoardSnapshotSlot::class, 'snapshot_id')
            ->orderBy('slot_order');
    }
}
