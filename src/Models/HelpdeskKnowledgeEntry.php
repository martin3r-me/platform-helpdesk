<?php

namespace Platform\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HelpdeskKnowledgeEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'helpdesk_board_id',
        'team_id',
        'title',
        'problem',
        'solution',
        'tags',
        'source_ticket_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            // team_id vom Board übernehmen
            if (!$model->team_id && $model->helpdesk_board_id) {
                $board = HelpdeskBoard::find($model->helpdesk_board_id);
                if ($board) {
                    $model->team_id = $board->team_id;
                }
            }
        });

        // KB-Eintrag (kuratierte Lösung) in den Board-Retrieval-Index — kuratierte Anker,
        // höher gewichtet als rohe Tickets. afterResponse (kein Queue-Zwang, blockiert nicht).
        static::saved(function (self $model): void {
            if (! $model->helpdesk_board_id || ! $model->problem) {
                return;
            }
            $id = $model->id;
            dispatch(function () use ($id): void {
                $e = self::find($id);
                if ($e) {
                    app(\Platform\Helpdesk\Services\TicketRetrievalService::class)->indexKnowledgeEntry($e);
                }
            })->afterResponse();
        });
    }

    public function helpdeskBoard(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBoard::class, 'helpdesk_board_id');
    }

    /**
     * Scope: Knowledge erbt die Sichtbarkeit ihres Boards (graph-erreichbar).
     * Kein Ersteller-Konzept (keine user_id); board-lose Einträge sind unsichtbar,
     * bis sie an ein Board gehängt werden (forcing function für die Ablage).
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $reachable = app(\Platform\Core\Authz\AuthzResolver::class)->reachableEntityIds($user, 'read');
        $reachableBoardIds = empty($reachable) ? [] : \Illuminate\Support\Facades\DB::table('authz_resource_link')
            ->where('resource_type', \Platform\Helpdesk\Models\HelpdeskBoard::class)
            ->whereIn('scope_id', $reachable)
            ->pluck('resource_id')
            ->all();

        return $query->whereIn('helpdesk_board_id', $reachableBoardIds);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'source_ticket_id');
    }
}
