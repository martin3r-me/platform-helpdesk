<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskAiResponse;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;
use Platform\Helpdesk\Services\TicketKnowledgeService;

class TicketResponseService
{
    protected ?\Platform\AiAssistant\Services\OpenAIService $openAIService = null;
    protected TicketKnowledgeService $knowledgeService;

    public function __construct(TicketKnowledgeService $knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
        
        if (class_exists(\Platform\AiAssistant\Services\OpenAIService::class)) {
            $this->openAIService = app(\Platform\AiAssistant\Services\OpenAIService::class);
        }
    }

}

