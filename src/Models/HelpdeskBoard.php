<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;

class HelpdeskBoard extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'order',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
        Log::info('HelpdeskBoard Model: booted() called!');
        
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'helpdesk_board_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(HelpdeskBoardSlot::class, 'helpdesk_board_id');
    }

    public function serviceHours(): HasMany
    {
        return $this->hasMany(HelpdeskBoardServiceHours::class, 'helpdesk_board_id');
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoardSla::class, 'helpdesk_board_sla_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
