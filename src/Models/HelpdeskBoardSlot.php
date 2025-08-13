<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;

class HelpdeskBoardSlot extends Model
{
    protected $fillable = [
        'uuid',
        'helpdesk_board_id',
        'name',
        'description',
        'order',
    ];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
        Log::info('HelpdeskBoardSlot Model: booted() called!');
        
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'helpdesk_board_slot_id');
    }

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }
}
