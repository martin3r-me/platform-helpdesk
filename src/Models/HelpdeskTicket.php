<?php

namespace Platform\Helpdesk\Models;

use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

use Platform\ActivityLog\Traits\LogsActivity;

class HelpdeskTicket extends Model
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
        'is_frog',
        'is_done',
        'order',
        'slot_order',
        'helpdesk_board_id',
        'helpdesk_board_slot_id',
        'helpdesk_ticket_group_id',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'story_points' => TicketStoryPoints::class,
        'due_date' => 'date'
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
}
