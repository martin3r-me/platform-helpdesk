<?php

return [
    'routing' => [
        'mode' => env('HELPDESK_MODE', 'path'),
        'prefix' => 'helpdesk',
    ],
    'guard' => 'web',

    'navigation' => [
        'route' => 'helpdesk.dashboard',
        'icon'  => 'heroicon-o-life-ring',
        'order' => 25,
    ],
    'billables' => [
        [
            // Pflicht: Das zu überwachende Model
            'model' => \Platform\Helpdesk\Models\HelpdeskTicket::class,

            // Abrechnungsart: Einzelobjekt pro Zeitraum (alternativ: 'flat_fee')
            'type' => 'per_item',

            // Für UI, Listen, Erklärungen:
            'label' => 'Helpdesk-Ticket',
            'description' => 'Jedes erstellte Ticket im Helpdesk verursacht tägliche Kosten nach Nutzung.',

            // PREISSTAFFELUNG: Ein Array mit mehreren Preisstufen!
            'pricing' => [
                [
                    'cost_per_day' => 0.0025,           // 2.5 Cent pro Ticket pro Tag
                    'start_date' => '2025-01-01',
                    'end_date' => null,
                ]
            ],

            // Kostenloses Kontingent (z.B. pro Tag)
            'free_quota' => null,               
            'min_cost' => null,               
            'max_cost' => null,              

            // Abrechnung & Zeitraum (idR identisch mit Pricing, aber für UI/Backend)
            'billing_period' => 'daily',      
            // Optional, falls ganzes Billable irgendwann endet
            'start_date' => '2026-01-01',
            'end_date' => null,               

            // Sonderlogik
            'trial_period_days' => 0,         
            'discount_percent' => 0,          
            'exempt_team_ids' => [],          

            // Interne Ordnung/Hilfen
            'priority' => 100,                
            'active' => true,                 
        ],
        [
            // Pflicht: Das zu überwachende Model
            'model' => \Platform\Helpdesk\Models\HelpdeskBoard::class,

            // Abrechnungsart: Einzelobjekt pro Zeitraum (alternativ: 'flat_fee')
            'type' => 'per_item',

            // Für UI, Listen, Erklärungen:
            'label' => 'Helpdesk-Board',
            'description' => 'Jedes erstellte Board im Helpdesk verursacht tägliche Kosten nach Nutzung.',

            // PREISSTAFFELUNG: Ein Array mit mehreren Preisstufen!
            'pricing' => [
                [
                    'cost_per_day' => 0.005,           // 5 Cent pro Board pro Tag
                    'start_date' => '2025-01-01',
                    'end_date' => null,
                ]
            ],

            // Kostenloses Kontingent (z.B. pro Tag)
            'free_quota' => null,               
            'min_cost' => null,               
            'max_cost' => null,              

            // Abrechnung & Zeitraum (idR identisch mit Pricing, aber für UI/Backend)
            'billing_period' => 'daily',      
            // Optional, falls ganzes Billable irgendwann endet
            'start_date' => '2026-01-01',
            'end_date' => null,               

            // Sonderlogik
            'trial_period_days' => 0,         
            'discount_percent' => 0,          
            'exempt_team_ids' => [],          

            // Interne Ordnung/Hilfen
            'priority' => 100,                
            'active' => true,                 
        ]
    ]
];