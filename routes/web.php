<?php

use Platform\Helpdesk\Livewire\Dashboard;
use Platform\Helpdesk\Livewire\MyTickets;
use Platform\Helpdesk\Livewire\Board;
use Platform\Helpdesk\Livewire\Ticket;
use Platform\Helpdesk\Livewire\Sla\Index as SlaIndex;
use Platform\Helpdesk\Livewire\Sla\Show as SlaShow;

Route::get('/', Dashboard::class)->name('helpdesk.dashboard');
Route::get('/my-tickets', MyTickets::class)->name('helpdesk.my-tickets');

// Model-Binding: Parameter == Modelname in camelCase
Route::get('/boards/{helpdeskBoard}', Board::class)
    ->name('helpdesk.boards.show');

Route::get('/tickets/{helpdeskTicket}', Ticket::class)
    ->name('helpdesk.tickets.show');

Route::post('/tickets/{helpdeskTicket}/unlock', function(\Platform\Helpdesk\Models\HelpdeskTicket $helpdeskTicket) {
    if ($helpdeskTicket->isLocked() && $helpdeskTicket->locked_by_user_id === \Illuminate\Support\Facades\Auth::id()) {
        $helpdeskTicket->unlock();
    }
    return response()->noContent();
})->name('helpdesk.tickets.unlock');

Route::get('/slas', SlaIndex::class)->name('helpdesk.slas.index');
Route::get('/slas/{helpdeskBoardSla}', SlaShow::class)->name('helpdesk.slas.show');

// Board-Health (Snapshot-Detail-Sicht pro Board)
Route::get('/boards/{helpdeskBoard}/health', \Platform\Helpdesk\Livewire\BoardHealth::class)
    ->name('helpdesk.boards.health');

// Health-Index (teamweite Board-Snapshot-Aggregat-Sicht)
Route::get('/health-index', \Platform\Helpdesk\Livewire\HealthIndex::class)
    ->name('helpdesk.health-index');

// Embedded Teams Config (Helpdesk) – Platzhalter
Route::get('/embedded/teams/config', function() {
    return view('helpdesk::embedded.teams-config');
})->name('helpdesk.embedded.teams.config');
