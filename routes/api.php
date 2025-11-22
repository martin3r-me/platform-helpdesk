<?php

use Illuminate\Support\Facades\Route;
use Platform\Helpdesk\Http\Controllers\Api\TicketDatawarehouseController;
use Platform\Helpdesk\Http\Controllers\Api\BoardDatawarehouseController;

/**
 * Helpdesk API Routes
 * 
 * Datawarehouse-Endpunkte für Tickets und Boards
 */
Route::get('/tickets/datawarehouse', [TicketDatawarehouseController::class, 'index']);
Route::get('/boards/datawarehouse', [BoardDatawarehouseController::class, 'index']);

