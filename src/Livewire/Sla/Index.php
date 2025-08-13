<?php

namespace Platform\Helpdesk\Livewire\Sla;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $modalShow = false;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    // Form fields für neues SLA
    public $name = '';
    public $description = '';
    public $is_active = true;
    public $response_time_hours = null;
    public $resolution_time_hours = null;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'response_time_hours' => 'nullable|integer|min:1',
            'resolution_time_hours' => 'nullable|integer|min:1',
        ];
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'name', 'description', 'is_active', 
            'response_time_hours', 'resolution_time_hours'
        ]);
    }

    public function createSla()
    {
        $this->validate();

        $sla = new HelpdeskBoardSla();
        $sla->name = $this->name;
        $sla->description = $this->description;
        $sla->is_active = $this->is_active;
        $sla->response_time_hours = $this->response_time_hours;
        $sla->resolution_time_hours = $this->resolution_time_hours;
        $sla->order = HelpdeskBoardSla::max('order') + 1;
        $sla->save();

        $this->closeCreateModal();
        $this->dispatch('slaCreated');
    }

    public function render()
    {
        $slas = HelpdeskBoardSla::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        return view('helpdesk::livewire.sla.index', [
            'slas' => $slas,
        ])->layout('platform::layouts.app');
    }
}
