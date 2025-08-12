<?php

namespace Platform\Helpdesk;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HelpdeskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Falls in Zukunft Artisan Commands o.ä. nötig sind, hier rein
    }

    public function boot(): void
    {

        $this->mergeConfigFrom(__DIR__.'/../config/helpdesk.php', 'helpdesk');

        // Modul sicher registrieren (nur wenn Config und Tabelle vorhanden)
        if (
            config()->has('helpdesk.routing') &&
            config()->has('helpdesk.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'helpdesk',
                'title'      => 'Helpdesk',
                'routing'    => config('helpdesk.routing'),
                'guard'      => config('helpdesk.guard'),
                'navigation' => config('helpdesk.navigation'),
                'sidebar'    => config('helpdesk.sidebar'),
            ]);
        }

        // Routen nur laden, wenn das Modul korrekt registriert wurde
        if (PlatformCore::getModule('helpdesk')) {
            ModuleRouter::group('helpdesk', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('helpdesk', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Config veröffentlichen & mergen
        $this->publishes([
            __DIR__.'/../config/helpdesk.php' => config_path('helpdesk.php'),
        ], 'config');

        

        // Views & Livewire-Komponenten
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'helpdesk');
        $this->registerLivewireComponents();
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

            // crm.contact.index aus crm + contact/index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}