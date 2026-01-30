<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardServiceHours;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;
use Platform\Helpdesk\Models\HelpdeskBoardErrorSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class BoardSettingsModal extends Component
{
    public $modalShow = false;
    public $board;
    public $teamUsers = [];
    public $serviceHours = [];
    public $availableSlas = [];
    public $showServiceHoursForm = false;
    
    // AI Settings
    public $activeTab = 'general'; // 'general', 'ai', 'service-hours', 'sla', 'error-tracking'
    public $aiSettings;
    public $availableModels = ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'];

    // Error Tracking Settings
    public $errorSettings;
    public $availableHttpCodes = [400, 401, 403, 404, 500, 502, 503, 504];
    public $newServiceZeit = [
        'name' => '',
        'description' => '',
        'is_active' => true,
        'use_auto_messages' => false,
        'auto_message_inside' => '',
        'auto_message_outside' => '',
        'service_hours' => []
    ];

    public function rules(): array
    {
        return [
            'board.name' => 'required|string|max:255',
            'board.description' => 'nullable|string',
            'board.helpdesk_board_sla_id' => 'nullable|exists:helpdesk_board_slas,id',
            'newServiceZeit.name' => 'required|string|max:255',
            'newServiceZeit.description' => 'nullable|string',
            'newServiceZeit.auto_message_inside' => 'nullable|string',
            'newServiceZeit.auto_message_outside' => 'nullable|string',
            // AI Settings Rules
            'aiSettings.auto_assignment_enabled' => 'nullable|boolean',
            'aiSettings.auto_assignment_confidence_threshold' => 'nullable|numeric|min:0|max:1',
            'aiSettings.ai_model' => 'nullable|string|max:50',
            'aiSettings.human_in_loop_enabled' => 'nullable|boolean',
            'aiSettings.human_in_loop_threshold' => 'nullable|numeric|min:0|max:1',
            'aiSettings.ai_enabled_for_escalated' => 'nullable|boolean',
            'aiSettings.knowledge_base_categories' => 'nullable|array',
            // Error Tracking Settings Rules
            'errorSettings.enabled' => 'nullable|boolean',
            'errorSettings.capture_codes' => 'nullable|array',
            'errorSettings.dedupe_window_hours' => 'nullable|integer|min:1|max:720',
            'errorSettings.auto_create_ticket' => 'nullable|boolean',
            'errorSettings.include_stack_trace' => 'nullable|boolean',
            'errorSettings.stack_trace_limit' => 'nullable|integer|min:1|max:200',
        ];
    }

    public function mount()
    {
        $this->modalShow = false;
        $this->newServiceZeit['service_hours'] = \Platform\Helpdesk\Models\HelpdeskBoardServiceHours::getDefaultServiceHours();
    }

    #[On('open-modal-board-settings')]
    public function openModalBoardSettings($boardId)
    {
        $this->board = HelpdeskBoard::with(['serviceHours', 'sla', 'aiSettings', 'errorSettings'])->findOrFail($boardId);

        // Teammitglieder holen
        $this->teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        // Service-Zeiten laden
        $this->serviceHours = $this->board->serviceHours()->orderBy('order')->get();
        
        // Verfügbare SLAs laden (team-scoped)
        $this->availableSlas = HelpdeskBoardSla::query()
            ->where('is_active', true)
            ->when(Auth::check() && Auth::user()->currentTeam, function ($query) {
                $query->where('team_id', Auth::user()->currentTeam->id);
            })
            ->orderBy('name')
            ->get();

        // AI Settings laden oder erstellen
        $this->loadAiSettings();

        // Error Settings laden oder erstellen
        $this->loadErrorSettings();

        $this->modalShow = true;
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function save()
    {
        $this->board->save();

        // AI Settings speichern
        if ($this->aiSettings) {
            $this->saveAiSettings();
        }

        // Error Settings speichern
        if ($this->errorSettings) {
            $this->saveErrorSettings();
        }

        $this->dispatch('boardUpdated');
        $this->dispatch('updateSidebar');
        $this->closeModal();
    }

    public function loadAiSettings(): void
    {
        $this->aiSettings = \Platform\Helpdesk\Models\HelpdeskBoardAiSettings::getOrCreateForBoard($this->board);
    }

    public function saveAiSettings(): void
    {
        if ($this->aiSettings) {
            $this->aiSettings->save();
        }
    }

    public function loadErrorSettings(): void
    {
        $this->errorSettings = HelpdeskBoardErrorSettings::getOrCreateForBoard($this->board);
    }

    public function saveErrorSettings(): void
    {
        if ($this->errorSettings) {
            $this->errorSettings->save();
        }
    }

    public function toggleHttpCode(int $code): void
    {
        if (!$this->errorSettings) {
            return;
        }

        $codes = $this->errorSettings->capture_codes ?? HelpdeskBoardErrorSettings::DEFAULT_CAPTURE_CODES;

        if (in_array($code, $codes)) {
            $codes = array_values(array_diff($codes, [$code]));
        } else {
            $codes[] = $code;
            sort($codes);
        }

        $this->errorSettings->capture_codes = $codes;
    }

    public function isHttpCodeEnabled(int $code): bool
    {
        if (!$this->errorSettings) {
            return false;
        }

        $codes = $this->errorSettings->capture_codes ?? HelpdeskBoardErrorSettings::DEFAULT_CAPTURE_CODES;

        return in_array($code, $codes);
    }

    public function addServiceHours()
    {
        $serviceHours = new HelpdeskBoardServiceHours();
        $serviceHours->helpdesk_board_id = $this->board->id;
        $serviceHours->name = $this->newServiceZeit['name'];
        $serviceHours->description = $this->newServiceZeit['description'];
        $serviceHours->is_active = $this->newServiceZeit['is_active'];
        $serviceHours->use_auto_messages = $this->newServiceZeit['use_auto_messages'];
        $serviceHours->auto_message_inside = $this->newServiceZeit['auto_message_inside'];
        $serviceHours->auto_message_outside = $this->newServiceZeit['auto_message_outside'];
        $serviceHours->service_hours = $this->newServiceZeit['service_hours'];
        $serviceHours->order = $this->board->serviceHours()->count();
        $serviceHours->save();

        $this->serviceHours = $this->board->serviceHours()->orderBy('order')->get();
        $this->reset('newServiceZeit');
        $this->showServiceHoursForm = false;
    }

    public function deleteServiceHours($serviceHoursId)
    {
        $serviceHours = HelpdeskBoardServiceHours::find($serviceHoursId);
        if ($serviceHours && $serviceHours->helpdesk_board_id == $this->board->id) {
            $serviceHours->delete();
            $this->serviceHours = $this->board->serviceHours()->orderBy('order')->get();
        }
    }

    public function toggleServiceHoursForm()
    {
        $this->showServiceHoursForm = !$this->showServiceHoursForm;
    }

    public function deleteBoard(): void
    {
        $this->board->delete();
        $this->reset('board');
        $this->dispatch('boardUpdated');
        $this->closeModal();
    }

    public function render()
    {
        return view('helpdesk::livewire.board-settings-modal');
    }
}
