<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Platform\Helpdesk\Models\HelpdeskTicket;

class Ticket extends Component
{
    public $ticket;
    public $dueDateModalShow = false;
    public $calendarMonth; // Aktueller Monat (1-12)
    public $calendarYear; // Aktuelles Jahr
    public $selectedDate; // Ausgewähltes Datum (Y-m-d)
    public $selectedTime; // Ausgewählte Zeit (H:i)
    public $selectedHour = 12; // Ausgewählte Stunde (0-23)
    public $selectedMinute = 0; // Ausgewählte Minute (0-59)
    public $githubRepositorySearch = ''; // Suchbegriff für GitHub Repositories
    public $newDodItem = ''; // Neuer DoD-Eintrag

    protected $rules = [
        'ticket.title' => 'required|string|max:255',
        'ticket.notes' => 'nullable|string',
        'ticket.dod' => 'nullable|array',

        'ticket.is_done' => 'boolean',
        'ticket.due_date' => 'nullable|date',
        'ticket.user_in_charge_id' => 'nullable|integer',
        'ticket.priority' => 'nullable|in:low,normal,high',
        'ticket.status' => 'nullable|in:open,in_progress,waiting,resolved,closed',
        'ticket.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'ticket.helpdesk_board_id' => 'nullable|integer',
    ];

    public function mount(HelpdeskTicket $helpdeskTicket)
    {
        $this->authorize('view', $helpdeskTicket);
        $this->ticket = $helpdeskTicket;
        $this->ticket->load('helpdeskBoard');
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->ticket),                                // z. B. 'Platform\Helpdesk\Models\HelpdeskTicket'
            'modelId' => $this->ticket->id,
            'subject' => $this->ticket->title,
            'description' => $this->ticket->notes ?? '',
            'url' => route('helpdesk.tickets.show', $this->ticket),            // absolute URL zum Ticket
            'source' => 'helpdesk.ticket.view',                                // eindeutiger Quell-Identifier (frei wählbar)
            'recipients' => [$this->ticket->user_in_charge_id],                // falls vorhanden, sonst leer
            'preferred_channel_id' => $this->ticket->comms_channel_id
                ?? $this->ticket->helpdeskBoard?->comms_channel_id
                ?? null,
            'capabilities' => [
                'manage_channels' => false,
                'threads' => true,
            ],
            'meta' => [
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
                'due_date' => $this->ticket->due_date,
                'story_points' => $this->ticket->story_points,
            ],
        ]);

        // Organization-Kontext setzen - nur Zeiten erlauben, keine Entity-Verknüpfung, keine Dimensionen
        $this->dispatch('organization', [
            'context_type' => get_class($this->ticket),
            'context_id' => $this->ticket->id,
            'linked_contexts' => $this->ticket->helpdeskBoard ? [['type' => get_class($this->ticket->helpdeskBoard), 'id' => $this->ticket->helpdeskBoard->id]] : [],
            'allow_time_entry' => true,
            'allow_entities' => false,
            'allow_dimensions' => false,
        ]);
    }

    public function updatedTicket($property, $value)
    {
        $this->validateOnly("ticket.$property");
        $this->ticket->save();
    }

    public function deleteTicket()
    {
        $this->authorize('delete', $this->ticket);
        $this->ticket->delete();
        return $this->redirect(route('helpdesk.my-tickets'), navigate: true);
    }

    public function toggleDone()
    {
        $this->ticket->is_done = !$this->ticket->is_done;
        $this->ticket->done_at = $this->ticket->is_done ? now() : null;
        $this->ticket->save();
    }

    public function toggleFrog()
    {
        $this->ticket->is_frog = !$this->ticket->is_frog;
        $this->ticket->save();
    }

    /**
     * Fügt einen neuen DoD-Eintrag hinzu
     */
    public function addDodItem()
    {
        if ($this->ticket->isLocked()) {
            return;
        }

        $text = trim($this->newDodItem);
        if (empty($text)) {
            return;
        }

        $dod = $this->ticket->dod ?? [];
        $dod[] = ['text' => $text, 'checked' => false];
        $this->ticket->dod = $dod;
        $this->ticket->save();
        $this->newDodItem = '';
    }

    /**
     * Entfernt einen DoD-Eintrag
     */
    public function removeDodItem($index)
    {
        if ($this->ticket->isLocked()) {
            return;
        }

        $dod = $this->ticket->dod ?? [];
        if (isset($dod[$index])) {
            array_splice($dod, $index, 1);
            $this->ticket->dod = array_values($dod);
            $this->ticket->save();
        }
    }

    /**
     * Wechselt den Status eines DoD-Eintrags
     */
    public function toggleDodItem($index)
    {
        if ($this->ticket->isLocked()) {
            return;
        }

        $dod = $this->ticket->dod ?? [];
        if (isset($dod[$index])) {
            $dod[$index]['checked'] = !($dod[$index]['checked'] ?? false);
            $this->ticket->dod = $dod;
            $this->ticket->save();
        }
    }

    /**
     * Aktualisiert den Text eines DoD-Eintrags
     */
    public function updateDodItem($index, $text)
    {
        if ($this->ticket->isLocked()) {
            return;
        }

        $dod = $this->ticket->dod ?? [];
        if (isset($dod[$index])) {
            $dod[$index]['text'] = trim($text);
            $this->ticket->dod = $dod;
            $this->ticket->save();
        }
    }

    /**
     * Verschiebt einen DoD-Eintrag nach oben oder unten
     */
    public function moveDodItem($index, $direction)
    {
        if ($this->ticket->isLocked()) {
            return;
        }

        $dod = $this->ticket->dod ?? [];
        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($newIndex < 0 || $newIndex >= count($dod)) {
            return;
        }

        // Tausche die Elemente
        $temp = $dod[$index];
        $dod[$index] = $dod[$newIndex];
        $dod[$newIndex] = $temp;

        $this->ticket->dod = $dod;
        $this->ticket->save();
    }

    public function lockTicket()
    {
        $this->authorize('lock', $this->ticket);
        $this->ticket->lock();
        session()->flash('success', 'Ticket wurde gesperrt.');
    }

    public function unlockTicket()
    {
        $this->authorize('unlock', $this->ticket);
        $this->ticket->unlock();
        session()->flash('success', 'Ticket wurde entsperrt.');
    }

    public function save()
    {
        // Gesperrte Tickets können nicht gespeichert werden
        if ($this->ticket->isLocked()) {
            session()->flash('error', 'Gesperrte Tickets können nicht bearbeitet werden.');
            return;
        }
        
        $this->validate();
        $this->ticket->save();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Ticket gespeichert',
        ]);
    }

    public function isDirty()
    {
        return $this->ticket->isDirty();
    }

    #[Computed]
    public function calendarDays()
    {
        $firstDay = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        
        // Start mit dem ersten Tag der Woche (Montag = 1)
        $startDate = $firstDay->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $endDate = $lastDay->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
        
        $days = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'isCurrentMonth' => $current->month == $this->calendarMonth,
                'isToday' => $current->isToday(),
                'isSelected' => $this->selectedDate === $current->format('Y-m-d'),
            ];
            $current->addDay();
        }
        
        return $days;
    }

    #[Computed]
    public function calendarMonthName()
    {
        return \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1)
            ->locale('de')
            ->isoFormat('MMMM YYYY');
    }

    public function openDueDateModal()
    {
        $this->authorize('update', $this->ticket);
        
        // Initialisiere Kalender mit aktuellem Datum oder heute
        if ($this->ticket->due_date) {
            $date = $this->ticket->due_date;
            $this->calendarMonth = $date->month;
            $this->calendarYear = $date->year;
            $this->selectedDate = $date->format('Y-m-d');
            $this->selectedTime = $date->format('H:i');
            $this->selectedHour = (int) $date->format('H');
            $this->selectedMinute = (int) $date->format('i');
        } else {
            $today = now();
            $this->calendarMonth = $today->month;
            $this->calendarYear = $today->year;
            $this->selectedDate = null;
            $this->selectedTime = $today->format('H:i');
            $this->selectedHour = (int) $today->format('H');
            $this->selectedMinute = (int) $today->format('i');
        }
        
        $this->dueDateModalShow = true;
    }

    public function closeDueDateModal()
    {
        // Verwerfe Änderungen - setze zurück auf aktuelles Datum
        if ($this->ticket->due_date) {
            $date = $this->ticket->due_date;
            $this->selectedDate = $date->format('Y-m-d');
            $this->selectedTime = $date->format('H:i');
            $this->selectedHour = (int) $date->format('H');
            $this->selectedMinute = (int) $date->format('i');
        } else {
            $this->selectedDate = null;
            $this->selectedTime = null;
            $this->selectedHour = 12;
            $this->selectedMinute = 0;
        }
        $this->dueDateModalShow = false;
    }

    public function previousMonth()
    {
        $date = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $date->subMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
    }

    public function nextMonth()
    {
        $date = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $date->addMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
    }

    public function selectDate($date)
    {
        $this->selectedDate = $date;
        // Standardzeit nach Datumsauswahl auf 12:00 setzen
        $this->selectedHour = 12;
        $this->selectedMinute = 0;
        $this->updateSelectedTime();
    }

    public function updatedSelectedHour($value)
    {
        $this->selectedHour = (int) $value;
        $this->updateSelectedTime();
    }

    public function updatedSelectedMinute($value)
    {
        $this->selectedMinute = (int) $value;
        $this->updateSelectedTime();
    }

    private function updateSelectedTime()
    {
        $this->selectedTime = sprintf('%02d:%02d', (int) $this->selectedHour, (int) $this->selectedMinute);
    }

    public function saveDueDate()
    {
        try {
            $this->authorize('update', $this->ticket);

            // Setze das Datum
            if (empty($this->selectedDate)) {
                $this->ticket->due_date = null;
            } else {
                $hour = $this->selectedHour ?? 12;
                $minute = $this->selectedMinute ?? 0;
                $time = sprintf('%02d:%02d', $hour, $minute);
                $this->ticket->due_date = \Carbon\Carbon::parse("{$this->selectedDate} {$time}");
            }

            // Speichere
            $this->ticket->save();
            
            // Aktualisiere das Model
            $this->ticket->refresh();
            
            // Aktualisiere den Selektions-State
            $this->selectedDate = $this->ticket->due_date ? $this->ticket->due_date->format('Y-m-d') : null;
            $this->selectedHour = $this->ticket->due_date ? (int) $this->ticket->due_date->format('H') : 12;
            $this->selectedMinute = $this->ticket->due_date ? (int) $this->ticket->due_date->format('i') : 0;
            
            // Modal schließen
            $this->dueDateModalShow = false;

            // Notification
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fälligkeitsdatum gespeichert',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung, dieses Ticket zu bearbeiten.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving due date: ' . $e->getMessage(), [
                'ticket_id' => $this->ticket->id,
                'selectedDate' => $this->selectedDate,
                'selectedHour' => $this->selectedHour ?? null,
                'selectedMinute' => $this->selectedMinute ?? null,
            ]);
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Speichern: ' . $e->getMessage(),
            ]);
        }
    }

    public function clearDueDate()
    {
        $this->authorize('update', $this->ticket);
        $this->ticket->due_date = null;
        $this->ticket->save();
        $this->ticket->refresh();
        
        $this->selectedDate = null;
        $this->selectedTime = null;
        $this->selectedHour = 12;
        $this->selectedMinute = 0;
        
        $this->dueDateModalShow = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fälligkeitsdatum entfernt',
        ]);
    }

    /**
     * GitHub Repository mit Ticket verknüpfen
     */
    public function attachGithubRepository($githubRepositoryId)
    {
        $this->authorize('update', $this->ticket);
        
        $githubRepository = \Platform\Integrations\Models\IntegrationsGithubRepository::findOrFail($githubRepositoryId);
        $service = app(\Platform\Integrations\Services\IntegrationAccountLinkService::class);
        
        if ($service->linkGithubRepository($githubRepository, $this->ticket)) {
            $this->ticket->refresh();
            session()->flash('success', 'GitHub Repository wurde erfolgreich mit dem Ticket verknüpft.');
        } else {
            session()->flash('error', 'GitHub Repository ist bereits mit diesem Ticket verknüpft.');
        }
    }

    /**
     * GitHub Repository von Ticket trennen
     */
    public function detachGithubRepository($githubRepositoryId)
    {
        $this->authorize('update', $this->ticket);
        
        $githubRepository = \Platform\Integrations\Models\IntegrationsGithubRepository::findOrFail($githubRepositoryId);
        $service = app(\Platform\Integrations\Services\IntegrationAccountLinkService::class);
        
        if ($service->unlinkGithubRepository($githubRepository, $this->ticket)) {
            $this->ticket->refresh();
            session()->flash('success', 'GitHub Repository wurde erfolgreich vom Ticket getrennt.');
        } else {
            session()->flash('error', 'GitHub Repository konnte nicht getrennt werden.');
        }
    }

    public function render()
    {        
        // Teammitglieder für Zuweisung laden
        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        // Verknüpfte GitHub Repositories dieses Tickets
        $linkedGithubRepositories = $this->ticket->githubRepositories();
        
        // Verfügbare GitHub Repositories des Users (noch nicht mit diesem Ticket verknüpft)
        $linkService = app(\Platform\Integrations\Services\IntegrationAccountLinkService::class);
        $allGithubRepositories = \Platform\Integrations\Models\IntegrationsGithubRepository::where('user_id', Auth::id())
            ->orderBy('full_name')
            ->get();
        
        $availableGithubRepositories = $allGithubRepositories->reject(function ($repo) use ($linkService) {
            return $linkService->isGithubRepositoryLinked($repo, $this->ticket);
        });

        // Filtere nach Suchbegriff
        if (!empty($this->githubRepositorySearch)) {
            $searchTerm = strtolower($this->githubRepositorySearch);
            $availableGithubRepositories = $availableGithubRepositories->filter(function ($repo) use ($searchTerm) {
                return str_contains(strtolower($repo->full_name), $searchTerm) ||
                       str_contains(strtolower($repo->name ?? ''), $searchTerm) ||
                       str_contains(strtolower($repo->description ?? ''), $searchTerm) ||
                       str_contains(strtolower($repo->owner ?? ''), $searchTerm);
            });
        }

        return view('helpdesk::livewire.ticket', [
            'teamUsers' => $teamUsers,
            'linkedGithubRepositories' => $linkedGithubRepositories,
            'availableGithubRepositories' => $availableGithubRepositories,
        ])->layout('platform::layouts.app');
    }
}
