<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskAiResponse;
use Platform\Comms\ChannelEmail\Services\EmailChannelPostmarkService;

class TicketCommsService
{
    /**
     * Sendet eine AI-generierte Response via Comms-System
     */
    public function sendResponse(
        HelpdeskTicket $ticket,
        HelpdeskAiResponse $aiResponse
    ): bool {
        try {
            // Finde aktiven Comms-Channel für Ticket
            $channelId = $ticket->comms_channel_id 
                ?? $ticket->helpdeskBoard?->comms_channel_id;
            
            if (!$channelId) {
                Log::warning('Kein Comms-Channel für Ticket gefunden', [
                    'ticket_id' => $ticket->id,
                ]);
                return false;
            }

            // Extrahiere Channel-Typ und ID (Format: "email:8")
            [$channelType, $channelAccountId] = explode(':', $channelId, 2) + [null, null];
            
            if ($channelType !== 'email' || !$channelAccountId) {
                Log::warning('Unbekannter Channel-Typ oder ungültige Channel-ID', [
                    'ticket_id' => $ticket->id,
                    'channel_id' => $channelId,
                ]);
                return false;
            }

            // Sende via Email-Channel
            if (class_exists(EmailChannelPostmarkService::class)) {
                $emailService = app(EmailChannelPostmarkService::class);
                
                // Finde Account
                $account = \Platform\Comms\ChannelEmail\Models\CommsChannelEmailAccount::find($channelAccountId);
                if (!$account) {
                    Log::error('Email-Account nicht gefunden', [
                        'ticket_id' => $ticket->id,
                        'account_id' => $channelAccountId,
                    ]);
                    return false;
                }

                // Finde Thread für Ticket
                $thread = $this->findOrCreateThreadForTicket($ticket, $account);
                
                if (!$thread) {
                    Log::error('Konnte Thread für Ticket nicht finden/erstellen', [
                        'ticket_id' => $ticket->id,
                    ]);
                    return false;
                }

                // Finde Empfänger (vom ursprünglichen Ticket-Ersteller oder Thread)
                $to = $this->getRecipientForTicket($ticket, $thread);
                if (!$to) {
                    Log::error('Kein Empfänger für Ticket gefunden', [
                        'ticket_id' => $ticket->id,
                    ]);
                    return false;
                }

                // Sende Antwort
                $token = $emailService->send(
                    account: $account,
                    to: $to,
                    subject: $ticket->title,
                    htmlBody: nl2br(e($aiResponse->response_text)),
                    textBody: $aiResponse->response_text,
                    files: [],
                    opt: [
                        'is_reply' => true,
                        'token' => $thread->token,
                        'context' => [
                            'model' => get_class($ticket),
                            'modelId' => $ticket->id,
                        ],
                    ],
                );

                if ($token) {
                    // Markiere als gesendet
                    $aiResponse->update(['sent_at' => now()]);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Fehler beim Senden der AI-Response', [
                'ticket_id' => $ticket->id,
                'response_id' => $aiResponse->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Findet oder erstellt einen Thread für das Ticket
     */
    protected function findOrCreateThreadForTicket(
        HelpdeskTicket $ticket,
        $account
    ): ?\Platform\Comms\ChannelEmail\Models\CommsChannelEmailThread {
        if (!class_exists(\Platform\Comms\ChannelEmail\Models\CommsChannelEmailThread::class)) {
            return null;
        }

        // Suche bestehenden Thread für dieses Ticket
        $thread = \Platform\Comms\ChannelEmail\Models\CommsChannelEmailThread::query()
            ->whereHas('contexts', function ($q) use ($ticket) {
                $q->where('context_type', get_class($ticket))
                  ->where('context_id', $ticket->id);
            })
            ->where('email_account_id', $account->id)
            ->first();

        if ($thread) {
            return $thread;
        }

        // Erstelle neuen Thread
        $thread = \Platform\Comms\ChannelEmail\Models\CommsChannelEmailThread::create([
            'email_account_id' => $account->id,
            'subject' => $ticket->title,
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
        ]);

        // Verknüpfe mit Ticket
        $thread->contexts()->create([
            'context_type' => get_class($ticket),
            'context_id' => $ticket->id,
        ]);

        return $thread;
    }

    /**
     * Findet Empfänger für Ticket (vom Thread oder Ticket-Ersteller)
     */
    protected function getRecipientForTicket(
        HelpdeskTicket $ticket,
        $thread
    ): ?string {
        // Versuche Empfänger vom Thread zu holen (letzte eingehende Mail)
        $lastInbound = $thread->inboundMails()->latest('received_at')->first();
        if ($lastInbound && $lastInbound->from) {
            // Extrahiere E-Mail-Adresse aus "Name <email@example.com>" Format
            if (preg_match('/<([^>]+)>/', $lastInbound->from, $matches)) {
                return $matches[1];
            }
            // Falls kein < > Format, verwende direkt
            if (filter_var($lastInbound->from, FILTER_VALIDATE_EMAIL)) {
                return $lastInbound->from;
            }
        }

        // Fallback: Versuche vom Ticket-Ersteller
        if ($ticket->user && $ticket->user->email) {
            return $ticket->user->email;
        }

        return null;
    }
}

