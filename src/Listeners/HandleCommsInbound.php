<?php

namespace Platform\Helpdesk\Listeners;

use Illuminate\Support\Facades\Log;
use Platform\Crm\Events\CommsInboundReceived;
use Platform\Crm\Models\CommsChannelContext;
use Platform\Crm\Models\CommsEmailInboundMail;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketPriority;

class HandleCommsInbound
{
    public function handle(CommsInboundReceived $event): void
    {
        if (!$event->isNewThread) {
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

            $event->thread->update([
                'context_model' => $ticket->getMorphClass(),
                'context_model_id' => $ticket->id,
            ]);

            // Attach email attachments (incl. CID inline images) to the ticket
            $this->attachEmailFilesToTicket($event->mail, $event->thread, $ticket);
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
