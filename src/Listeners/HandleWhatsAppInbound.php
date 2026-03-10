<?php

namespace Platform\Helpdesk\Listeners;

use Illuminate\Support\Facades\Log;
use Platform\Crm\Events\CommsWhatsAppInboundReceived;
use Platform\Crm\Models\CommsChannelContext;
use Platform\Crm\Models\CommsWhatsAppMessage;
use Platform\Crm\Models\CommsWhatsAppThread;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketPriority;

class HandleWhatsAppInbound
{
    public function handle(CommsWhatsAppInboundReceived $event): void
    {
        $channel = $event->channel;
        $thread = $event->thread;
        $message = $event->message;

        if (!$event->isNewThread) {
            $this->handleFollowUp($thread, $message);
            return;
        }

        $contexts = CommsChannelContext::query()
            ->where('comms_channel_id', $channel->id)
            ->where('context_model', HelpdeskBoard::class)
            ->get();

        foreach ($contexts as $context) {
            $board = HelpdeskBoard::find($context->context_model_id);

            if (!$board) {
                continue;
            }

            $title = $message->body
                ? mb_substr($message->body, 0, 100)
                : 'Inbound WhatsApp';

            $ticket = HelpdeskTicket::create([
                'title' => $title,
                'notes' => $message->body ? mb_substr($message->body, 0, 5000) : null,
                'helpdesk_board_id' => $board->id,
                'team_id' => $board->team_id,
                'user_id' => $board->user_id,
                'priority' => TicketPriority::Normal,
            ]);

            $thread->update([
                'context_model' => $ticket->getMorphClass(),
                'context_model_id' => $ticket->id,
            ]);

            $this->attachWhatsAppFilesToTicket($message, $thread, $ticket);
        }
    }

    private function handleFollowUp(CommsWhatsAppThread $thread, CommsWhatsAppMessage $message): void
    {
        if ($thread->context_model !== (new HelpdeskTicket)->getMorphClass()) {
            return;
        }

        $ticket = HelpdeskTicket::find($thread->context_model_id);

        if (!$ticket) {
            return;
        }

        $this->attachWhatsAppFilesToTicket($message, $thread, $ticket);
    }

    private function attachWhatsAppFilesToTicket(
        CommsWhatsAppMessage $message,
        CommsWhatsAppThread $thread,
        HelpdeskTicket $ticket,
    ): void {
        if (!method_exists($message, 'getFileReferencesArray')) {
            return;
        }

        try {
            $fileRefs = $message->getFileReferencesArray();
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
                    'source' => 'helpdesk_inbound_whatsapp',
                    'whatsapp_message_id' => $message->id,
                    'thread_id' => $thread->id,
                ]);
            }

            Log::info('[Helpdesk] WhatsApp attachments linked to ticket', [
                'ticket_id' => $ticket->id,
                'file_count' => count($fileRefs),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Helpdesk] Failed to attach WhatsApp files to ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
