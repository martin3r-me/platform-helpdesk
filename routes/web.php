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

Route::get('/slas', SlaIndex::class)->name('helpdesk.slas.index');
Route::get('/slas/{helpdeskBoardSla}', SlaShow::class)->name('helpdesk.slas.show');
