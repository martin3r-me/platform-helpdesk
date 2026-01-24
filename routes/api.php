<?php

use Illuminate\Support\Facades\Route;
use Platform\Helpdesk\Http\Controllers\Api\TicketDatawarehouseController;
use Platform\Helpdesk\Http\Controllers\Api\BoardDatawarehouseController;
use Platform\Helpdesk\Http\Controllers\Api\GithubRepositoryTicketController;

/**
 * Helpdesk API Routes
 * 
 * Datawarehouse-Endpunkte für Tickets und Boards
 */
Route::get('/tickets/datawarehouse', [TicketDatawarehouseController::class, 'index']);
Route::get('/tickets/datawarehouse/health', [TicketDatawarehouseController::class, 'health']);
Route::get('/boards/datawarehouse', [BoardDatawarehouseController::class, 'index']);
Route::get('/boards/datawarehouse/health', [BoardDatawarehouseController::class, 'health']);

/**
 * GitHub Repository-bezogene Ticket-Endpunkte
 */
Route::get('/tickets/github-repository/next-open', [GithubRepositoryTicketController::class, 'getNextOpenTicket']);
