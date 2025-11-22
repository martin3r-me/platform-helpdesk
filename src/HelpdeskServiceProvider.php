<?php

namespace Platform\Helpdesk;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;

// Optional: Models und Policies absichern
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Policies\HelpdeskTicketPolicy;
use Platform\Helpdesk\Policies\HelpdeskBoardPolicy;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HelpdeskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Commands registrieren
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Helpdesk\Console\Commands\CheckTicketEscalationsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
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

            if (!class_exists($class)) {
                continue;
            }

            // helpdesk.ticket.index aus helpdesk + ticket/index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}