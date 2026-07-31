<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Board-seitige, kuratierbare Ticket-Kategorie. `description` (Grenze) + `examples`
 * (Few-Shot-Anker) sind die semantische Grundlage fürs Einordnen — keine Stichworte.
 */
class HelpdeskBoardCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'helpdesk_board_id',
        'name',
        'description',
        'examples',
        'order',
        'is_active',
    ];

    protected $casts = [
        'uuid' => 'string',
        'examples' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'helpdesk_board_category_id');
    }
}
