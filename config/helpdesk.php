<?php
return [
    'routing' => [
        'mode' => env('HELPDESK_MODE', 'subdomain'),  // Standard: Subdomain
        'prefix' => 'helpdesk',                       // Wird nur genutzt, wenn 'path'
    ],
    'guard' => 'web',

    'navigation' => [
        'route' => 'helpdesk.dashboard',
        'icon'  => 'heroicon-o-ticket',
        'order' => 30,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'helpdesk.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Tickets',
                    'route' => 'helpdesk.tickets',
                    'icon'  => 'heroicon-o-ticket',
                ],
            ],
        ],
        [
            'group' => 'Administration',
            'items' => [
                [
                    'label' => 'Einstellungen',
                    'route' => 'helpdesk.settings',
                    'icon'  => 'heroicon-o-cog',
                ],
            ],
        ],
    ],
];