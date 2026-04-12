<?php

namespace Platform\Helpdesk\Listeners;

use Illuminate\Support\Facades\Log;
use Platform\Crm\Events\CommsInboundReceived;
use Platform\Crm\Models\CommsChannelContext;
use Platform\Crm\Models\CommsEmailInboundMail;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Notifications\Models\NotificationsNotice;

class HandleCommsInbound
{
    public function handle(CommsInboundReceived $event): void
    {
        try {
            if (!$event->isNewThread) {
                $this->handleFollowUp($event);
                return;
            }

            $contexts = CommsChannelContext::query()
                ->where('comms_channel_id', $event->channel->id)
                ->where('context_model', HelpdeskBoard::class)
                ->get();

            foreach ($contexts as $context) {
                $board = HelpdeskBoard::find($context->context_model_id);

                if (!$board) {
                    continue;
                }

                $ticket = HelpdeskTicket::create([
                    'title' => $event->mail->subject ?: 'Inbound E-Mail',
                    'notes' => $event->mail->text_body ? mb_substr($event->mail->text_body, 0, 5000) : null,
                    'helpdesk_board_id' => $board->id,
                    'team_id' => $board->team_id,
                    'user_id' => $board->user_id,
                    'priority' => TicketPriority::Normal,
                ]);

                // Notify the responsible user (board owner) about the new ticket
                $this->notifyNewTicket($ticket, $board, $event->mail->from);

                $event->thread->addContext($ticket->getMorphClass(), $ticket->id, 'helpdesk_inbound');

                // Stamp ticket marker [#ID] on thread + inbound mail subject immediately,
                // so that the conversation is tagged from the very first inbound message
                // (not only after the first outbound reply).
                $marker = "[#{$ticket->id}]";
                $threadSubject = (string) ($event->thread->subject ?? '');
                if (!preg_match('/\[#\d+\]/', $threadSubject)) {
                    $event->thread->updateQuietly([
                        'subject' => trim($marker . ' ' . $threadSubject),
                    ]);
                }
                $mailSubject = (string) ($event->mail->subject ?? '');
                if (!preg_match('/\[#\d+\]/', $mailSubject)) {
                    $event->mail->updateQuietly([
                        'subject' => trim($marker . ' ' . $mailSubject),
                    ]);
                }

                // Attach email attachments (incl. CID inline images) to the ticket
                $this->attachEmailFilesToTicket($event->mail, $event->thread, $ticket);
            }
        } catch (\Throwable $e) {
            Log::error('[Helpdesk] Failed to process email inbound', [
                'channel_id' => $event->channel->id,
                'mail_id' => $event->mail->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleFollowUp(CommsInboundReceived $event): void
    {
        try {
            $thread = $event->thread;

            // Find the ticket context from the pivot table
            $ticketMorphClass = (new HelpdeskTicket)->getMorphClass();
            $ticketContext = $thread->contexts()
                ->where('context_model', $ticketMorphClass)
                ->first();

            if (!$ticketContext) {
                return;
            }

            $ticket = HelpdeskTicket::find($ticketContext->context_model_id);

            if (!$ticket) {
                return;
            }

            $this->attachEmailFilesToTicket($event->mail, $thread, $ticket);

            // Notify the responsible user about the follow-up
            $notifyUserId = $ticket->user_in_charge_id ?: $ticket->user_id;
            if ($notifyUserId) {
                $this->createNotice(
                    $ticket,
                    $notifyUserId,
                    $ticket->team_id,
                    'Neue Antwort: ' . ($ticket->title ?: 'Ticket #' . $ticket->id),
                    $event->mail->from,
                );
            }
        } catch (\Throwable $e) {
            Log::error('[Helpdesk] Failed to process email follow-up', [
                'channel_id' => $event->channel->id,
                'mail_id' => $event->mail->id,
                'thread_id' => $event->thread->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyNewTicket(HelpdeskTicket $ticket, HelpdeskBoard $board, ?string $from): void
    {
        $notifyUserId = $ticket->user_in_charge_id ?: $board->user_id;
        if (!$notifyUserId) {
            return;
        }

        $this->createNotice(
            $ticket,
            $notifyUserId,
            $board->team_id,
            $ticket->title ?: 'Neues Ticket',
            $from,
        );
    }

    private function createNotice(HelpdeskTicket $ticket, int $userId, ?int $teamId, string $message, ?string $from): void
    {
        try {
            if (!class_exists(NotificationsNotice::class)) {
                return;
            }

            NotificationsNotice::create([
                'notice_type' => 'helpdesk_ticket',
                'title' => 'Neues Helpdesk Ticket',
                'message' => $message,
                'user_id' => $userId,
                'team_id' => $teamId,
                'noticable_type' => $ticket->getMorphClass(),
                'noticable_id' => $ticket->id,
                'metadata' => [
                    'source' => 'inbound_email',
                    'from' => $from,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk] Failed to create ticket notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Attach email attachments to the ticket as ContextFiles.
     * Pattern analog zu HandleCommsInboundForRecruiting::attachEmailFilesToApplicant()
     */
    private function attachEmailFilesToTicket(
        CommsEmailInboundMail $mail,
        $thread,
        HelpdeskTicket $ticket,
    ): void {
        if (!method_exists($mail, 'getFileReferencesArray')) {
            return;
        }

        try {
            $fileRefs = $mail->getFileReferencesArray();
            if (empty($fileRefs)) {
                return;
            }

            foreach ($fileRefs as $fileRef) {
                $contextFileId = $fileRef['context_file_id'] ?? $fileRef['id'] ?? null;
                if (!$contextFileId) {
                    continue;
                }

                $ticket->addFileReference($contextFileId, [
                    'title' => $fileRef['title'] ?? 'Anhang',
                    'source' => 'helpdesk_inbound_mail',
                    'inbound_mail_id' => $mail->id,
                    'thread_id' => $thread->id,
                ]);
            }

            Log::info('[Helpdesk] Email attachments linked to ticket', [
                'ticket_id' => $ticket->id,
                'file_count' => count($fileRefs),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk] Failed to attach email files to ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
