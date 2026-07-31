<?php

use Illuminate\Support\Facades\Route;
use Platform\Helpdesk\Http\Controllers\Api\TicketDatawarehouseController;
use Platform\Helpdesk\Http\Controllers\Api\BoardDatawarehouseController;
use Platform\Helpdesk\Http\Controllers\Api\GithubRepositoryTicketController;
use Platform\Helpdesk\Http\Controllers\Api\AgentController;

/**
 * Helpdesk API Routes
 * 
 * Datawarehouse-Endpunkte für Tickets und Boards
 */
/**
 * Support-Worker Agent-API (frisch, token-authentifiziert) — Schritt 1: Board-Auswahl.
 */
Route::prefix('agent')->middleware('auth:api')->group(function () {
    Route::get('/boards', [AgentController::class, 'boards'])->name('helpdesk.api.agent.boards');
    Route::post('/tickets/next-backlog', [AgentController::class, 'nextBacklogTicket'])->name('helpdesk.api.agent.next-backlog');
    Route::post('/tickets/{id}/triage', [AgentController::class, 'triageTicket'])->name('helpdesk.api.agent.triage');
});

Route::get('/tickets/datawarehouse', [TicketDatawarehouseController::class, 'index']);
Route::get('/tickets/datawarehouse/health', [TicketDatawarehouseController::class, 'health']);
Route::get('/boards/datawarehouse', [BoardDatawarehouseController::class, 'index']);
Route::get('/boards/datawarehouse/health', [BoardDatawarehouseController::class, 'health']);

/**
 * GitHub Repository-bezogene Ticket-Endpunkte
 */
Route::get('/tickets/github-repository/next-open', [GithubRepositoryTicketController::class, 'getNextOpenTicket']);
Route::post('/tickets/mark-done', [GithubRepositoryTicketController::class, 'markTicketAsDone']);
Route::post('/tickets/mark-checked', [GithubRepositoryTicketController::class, 'markTicketAsChecked']);
Route::post('/tickets/unlock', [GithubRepositoryTicketController::class, 'unlockTicket']);
Route::post('/tickets/add-plan', [GithubRepositoryTicketController::class, 'addPlanToTicket']);
Route::post('/tickets/analyze-code', [GithubRepositoryTicketController::class, 'analyzeCodeWithClaude']);
Route::get('/tickets/get', [GithubRepositoryTicketController::class, 'getTicket']);