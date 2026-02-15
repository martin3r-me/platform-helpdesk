<?php

namespace Platform\Helpdesk\Listeners;

use Platform\Core\Events\CommsInboundReceived;
use Platform\Core\Models\CommsChannelContext;
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
                'context_model' => HelpdeskTicket::class,
                'context_model_id' => $ticket->id,
            ]);
        }
    }
}
