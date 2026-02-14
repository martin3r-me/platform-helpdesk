<?php

namespace Platform\Helpdesk;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Core\Events\CommsInboundReceived;
use Platform\Helpdesk\Listeners\HandleCommsInbound;

// Optional: Models und Policies absichern
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Policies\HelpdeskTicketPolicy;
use Platform\Helpdesk\Policies\HelpdeskBoardPolicy;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Platform\Helpdesk\Contracts\ErrorTrackerContract;
use Platform\Helpdesk\Services\ErrorTrackingService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HelpdeskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Error Tracking Service registrieren
        $this->app->singleton('helpdesk.error-tracker', ErrorTrackingService::class);
        $this->app->singleton(ErrorTrackerContract::class, ErrorTrackingService::class);

        // Commands registrieren
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Helpdesk\Console\Commands\CheckTicketEscalationsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Morph-Map für Extra-Fields (damit core.extra_fields.PUT die Entity findet)
        Relation::morphMap([
            'helpdesk_ticket' => \Platform\Helpdesk\Models\HelpdeskTicket::class,
        ]);

        // Modul-Registrierung nur, wenn Config & Tabelle vorhanden
        if (
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'helpdesk',
                'title'      => 'Helpdesk',
                'routing'    => config('helpdesk.routing'),
                'guard'      => config('helpdesk.guard'),
                'navigation' => config('helpdesk.navigation'),
                'sidebar'    => config('helpdesk.sidebar'),
                'billables'  => config('helpdesk.billables'),
            ]);
        }

        // Routen nur laden, wenn das Modul registriert wurde
        if (PlatformCore::getModule('helpdesk')) {
            ModuleRouter::group('helpdesk', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('helpdesk', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // API-Routen registrieren
            ModuleRouter::apiGroup('helpdesk', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Config veröffentlichen & zusammenführen
        $this->publishes([
            __DIR__.'/../config/helpdesk.php' => config_path('helpdesk.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../config/helpdesk.php', 'helpdesk');

        // Migrations, Views, Livewire-Komponenten
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'helpdesk');
        $this->registerLivewireComponents();

        // Policies nur registrieren, wenn Klassen vorhanden sind
        if (class_exists(HelpdeskTicket::class) && class_exists(HelpdeskTicketPolicy::class)) {
            Gate::policy(HelpdeskTicket::class, HelpdeskTicketPolicy::class);
        }

        if (class_exists(HelpdeskBoard::class) && class_exists(HelpdeskBoardPolicy::class)) {
            Gate::policy(HelpdeskBoard::class, HelpdeskBoardPolicy::class);
        }

        // Inbound-Listener registrieren (Ticket-Erstellung bei E-Mail-Eingang)
        Event::listen(CommsInboundReceived::class, HandleCommsInbound::class);

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();

        // Error Tracking in Exception Handler integrieren
        $this->registerErrorTracking();
    }
    
    /**
     * Registriert Helpdesk-Tools für die AI/Chat-Funktionalität
     * 
     * HINWEIS: Tools werden auch automatisch via Auto-Discovery gefunden,
     * aber manuelle Registrierung stellt sicher, dass sie verfügbar sind.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            
            // Overview-Tool
            $registry->register(new \Platform\Helpdesk\Tools\HelpdeskOverviewTool());
            
            // Board-Tools
            $registry->register(new \Platform\Helpdesk\Tools\CreateBoardTool());
            $registry->register(new \Platform\Helpdesk\Tools\ListBoardsTool());
            $registry->register(new \Platform\Helpdesk\Tools\GetBoardTool());
            $registry->register(new \Platform\Helpdesk\Tools\UpdateBoardTool());
            $registry->register(new \Platform\Helpdesk\Tools\DeleteBoardTool());
            
            // Ticket-Tools
            $registry->register(new \Platform\Helpdesk\Tools\CreateTicketTool());
            $registry->register(new \Platform\Helpdesk\Tools\ListTicketsTool());
            $registry->register(new \Platform\Helpdesk\Tools\GetTicketTool());
            $registry->register(new \Platform\Helpdesk\Tools\UpdateTicketTool());
            $registry->register(new \Platform\Helpdesk\Tools\DeleteTicketTool());
            $registry->register(new \Platform\Helpdesk\Tools\TicketDodTool());

            // Ticket Bulk-Tools
            $registry->register(new \Platform\Helpdesk\Tools\BulkCreateTicketsTool());
            $registry->register(new \Platform\Helpdesk\Tools\BulkUpdateTicketsTool());
            $registry->register(new \Platform\Helpdesk\Tools\BulkDeleteTicketsTool());

            // GitHub-Tools
            $registry->register(new \Platform\Helpdesk\Tools\ListGithubRepositoriesTool());
            $registry->register(new \Platform\Helpdesk\Tools\LinkTicketGithubRepositoryTool());
            $registry->register(new \Platform\Helpdesk\Tools\UnlinkTicketGithubRepositoryTool());
        } catch (\Throwable $e) {
            // Silent fail - ToolRegistry möglicherweise nicht verfügbar
            \Log::warning('Helpdesk: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Registriert Error Tracking im Exception Handler
     *
     * Sobald das Helpdesk-Modul installiert ist, werden Exceptions
     * automatisch erfasst (sofern Error Tracking für ein Board aktiviert ist).
     */
    protected function registerErrorTracking(): void
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            // Laravel 8+ hat reportable() Methode
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    $this->captureException($e);
                });
            }
        } catch (\Throwable $e) {
            // Silent fail - Exception Handler möglicherweise nicht verfügbar
            \Log::debug('Helpdesk: Error Tracking Registrierung übersprungen', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Erfasst eine Exception über den Error Tracking Service
     */
    protected function captureException(\Throwable $e): void
    {
        try {
            // Error Tracker Service holen
            if (!$this->app->bound('helpdesk.error-tracker')) {
                return;
            }

            $tracker = $this->app->make('helpdesk.error-tracker');

            // HTTP Status Code ermitteln
            $httpCode = null;
            if ($e instanceof HttpExceptionInterface) {
                $httpCode = $e->getStatusCode();
            } elseif (method_exists($e, 'getStatusCode')) {
                $httpCode = $e->getStatusCode();
            }

            // Prüfen ob Console-Errors erfasst werden sollen
            $isConsole = $this->app->runningInConsole() && !$this->app->runningUnitTests();

            // Exception erfassen (Console-Flag wird an Service übergeben)
            $tracker->capture($e, [
                'http_code' => $httpCode,
                'is_console' => $isConsole,
            ]);
        } catch (\Throwable $captureError) {
            // Fehler beim Erfassen dürfen die App nicht beeinträchtigen
            \Log::debug('Helpdesk: Exception konnte nicht erfasst werden', [
                'error' => $captureError->getMessage(),
            ]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Helpdesk\\Livewire';
        $prefix = 'helpdesk';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            // Versuche die Klasse zu prüfen, mit Fehlerbehandlung für Autoloader-Probleme
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Wenn beim Laden ein Fehler auftritt (z.B. fehlende Datei), überspringe diese Datei
                \Log::warning('Helpdesk: Konnte Livewire-Komponente nicht laden', [
                    'class' => $class,
                    'file' => $file->getPathname(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            // helpdesk.ticket.index aus helpdesk + ticket/index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}