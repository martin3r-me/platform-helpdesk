<?php

use Illuminate\Support\Facades\Route;

Route::get('/', Platform\Helpdesk\Livewire\Dashboard::class)->name('helpdesk.dashboard');
Route::get('/tickets', Platform\Helpdesk\Livewire\Tickets::class)->name('helpdesk.tickets');
Route::get('/settings', Platform\Helpdesk\Livewire\Settings::class)->name('helpdesk.settings');
