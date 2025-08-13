<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;

class HelpdeskBoardServiceHours extends Model
{
    protected $fillable = [
        'uuid',
        'helpdesk_board_id',
        'name',
        'description',
        'is_active',
        'service_hours',
        'auto_message_inside',
        'auto_message_outside',
        'use_auto_messages',
        'order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_active' => 'boolean',
        'service_hours' => 'array',
        'use_auto_messages' => 'boolean',
    ];

    protected static function booted(): void
    {
        Log::info('HelpdeskBoardServiceHours Model: booted() called!');
        
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

    /**
     * Prüft, ob aktuell Service Hours aktiv sind
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->service_hours || empty($this->service_hours)) {
            return true; // Keine Zeiten definiert = immer aktiv
        }

        $now = now();
        $dayOfWeek = $now->dayOfWeek; // 0 = Sonntag, 1 = Montag, etc.
        $time = $now->format('H:i');

        // Prüfe ob aktueller Tag und Zeit in den Service Hours liegt
        foreach ($this->service_hours as $schedule) {
            if ($schedule['day'] == $dayOfWeek) {
                if ($time >= $schedule['start'] && $time <= $schedule['end']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gibt die passende Auto-Nachricht zurück
     */
    public function getAutoMessage(): ?string
    {
        if (!$this->use_auto_messages) {
            return null;
        }

        if ($this->isCurrentlyActive()) {
            return $this->auto_message_inside;
        } else {
            return $this->auto_message_outside;
        }
    }

    /**
     * Erstellt Standard-Service-Hours für Mo-Fr 9-17 Uhr
     */
    public static function getDefaultServiceHours(): array
    {
        return [
            [
                'day' => 1, // Montag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => true
            ],
            [
                'day' => 2, // Dienstag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => true
            ],
            [
                'day' => 3, // Mittwoch
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => true
            ],
            [
                'day' => 4, // Donnerstag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => true
            ],
            [
                'day' => 5, // Freitag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => true
            ],
            [
                'day' => 6, // Samstag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => false
            ],
            [
                'day' => 0, // Sonntag
                'start' => '09:00',
                'end' => '17:00',
                'enabled' => false
            ]
        ];
    }

    /**
     * Gibt die Wochentage als Array zurück
     */
    public static function getWeekDays(): array
    {
        return [
            1 => 'Montag',
            2 => 'Dienstag', 
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            0 => 'Sonntag'
        ];
    }

    /**
     * Formatiert die Service Hours für die Anzeige
     */
    public function getFormattedSchedule(): string
    {
        if (!$this->service_hours || empty($this->service_hours)) {
            return '24/7 verfügbar';
        }

        $enabledDays = collect($this->service_hours)->filter(fn($day) => $day['enabled'] ?? false);
        
        if ($enabledDays->isEmpty()) {
            return 'Nicht verfügbar';
        }

        $weekDays = self::getWeekDays();
        $formatted = [];

        foreach ($enabledDays as $day) {
            $dayName = $weekDays[$day['day']] ?? 'Unbekannt';
            $time = $day['start'] . ' - ' . $day['end'];
            $formatted[] = "$dayName: $time";
        }

        return implode(', ', $formatted);
    }
}
