<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskKnowledgeBase;
use Illuminate\Support\Collection;

class TicketKnowledgeService
{
    /**
     * Sucht ähnliche Einträge in der Knowledge Base
     */
    public function searchSimilar(HelpdeskTicket $ticket, array $categories = []): Collection
    {
        $query = HelpdeskKnowledgeBase::query()
            ->where('language', 'de')
            ->where(function ($q) use ($ticket) {
                $q->where('team_id', $ticket->team_id)
                  ->orWhereNull('team_id');
            });

        if ($ticket->helpdesk_board_id) {
            $query->where(function ($q) use ($ticket) {
                $q->where('helpdesk_board_id', $ticket->helpdesk_board_id)
                  ->orWhereNull('helpdesk_board_id');
            });
        }

        if (!empty($categories)) {
            $query->whereIn('category', $categories);
        }

        // Sortiere nach Erfolgsrate und Nutzung
        $query->orderBy('success_rate', 'desc')
              ->orderBy('usage_count', 'desc')
              ->limit(10);

        return $query->get();
    }

    /**
     * Fügt einen neuen Knowledge Base Eintrag hinzu
     */
    public function addEntry(array $data): HelpdeskKnowledgeBase
    {
        return HelpdeskKnowledgeBase::create([
            'team_id' => $data['team_id'] ?? null,
            'helpdesk_board_id' => $data['helpdesk_board_id'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'category' => $data['category'] ?? null,
            'tags' => $data['tags'] ?? [],
            'language' => $data['language'] ?? 'de',
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ]);
    }

    /**
     * Aktualisiert die Erfolgsrate eines KB-Eintrags
     */
    public function updateSuccessRate(int $entryId, bool $success): void
    {
        $entry = HelpdeskKnowledgeBase::find($entryId);
        if (!$entry) {
            return;
        }

        $entry->increment('usage_count');
        
        // Berechne neue Erfolgsrate (einfaches Moving Average)
        $currentRate = $entry->success_rate ?? 0.0;
        $usageCount = $entry->usage_count;
        
        // Gewichteter Durchschnitt: Neue Rate = (Alte Rate * (Count-1) + Erfolg) / Count
        $newRate = ($currentRate * ($usageCount - 1) + ($success ? 1.0 : 0.0)) / $usageCount;
        
        $entry->update(['success_rate' => $newRate]);
    }

    /**
     * Extrahiert Learnings aus einem gelösten Ticket
     */
    public function extractFromResolution(HelpdeskTicket $ticket): ?HelpdeskKnowledgeBase
    {
        if (!$ticket->is_done || !$ticket->resolution) {
            return null;
        }

        $resolution = $ticket->resolution;
        
        // Nur wenn User bestätigt hat oder AI-generiert und erfolgreich
        if (!$resolution->user_confirmed && !$resolution->ai_generated) {
            return null;
        }

        // Prüfe ob bereits ein ähnlicher Eintrag existiert
        $existing = HelpdeskKnowledgeBase::query()
            ->where('team_id', $ticket->team_id)
            ->where('category', $ticket->aiClassifications()->first()?->category)
            ->where('title', 'like', '%' . substr($ticket->title, 0, 50) . '%')
            ->first();

        if ($existing) {
            // Aktualisiere bestehenden Eintrag
            $this->updateSuccessRate($existing->id, true);
            return $existing;
        }

        // Erstelle neuen Eintrag
        $category = $ticket->aiClassifications()->first()?->category ?? 'Allgemein';
        
        return $this->addEntry([
            'team_id' => $ticket->team_id,
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'title' => $ticket->title,
            'content' => $resolution->resolution_text,
            'category' => $category,
            'tags' => [],
            'language' => 'de',
            'created_by_user_id' => $ticket->user_in_charge_id,
        ]);
    }
}

