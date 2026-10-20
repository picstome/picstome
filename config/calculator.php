<?php

return [
    'defaults' => [
        'settings' => [
            'hourly_rate' => 50,
            'sessions_per_month' => 10,
            'vat_percent' => 21,
            'income_tax_percent' => 15,
            'income_tax_withholding' => false,
            'guide_dismissed_at' => null,
        ],

        'fixed_expenses' => [
            ['name' => 'Self-Employment Quota', 'amount' => 260],
            ['name' => 'Insurances', 'amount' => 100],
            ['name' => 'Software Subscriptions', 'amount' => 50],
            ['name' => 'Website Hosting', 'amount' => 15],
        ],

        'session_expenses' => [
            ['name' => 'Props & Styling', 'amount' => 20],
            ['name' => 'Transportation', 'amount' => 15],
            ['name' => 'Studio Rental', 'amount' => 50],
        ],

        'equipment' => [
            ['name' => 'Camera Body', 'price' => 1200, 'resale_value' => 0, 'useful_life_years' => 6],
            ['name' => 'Lens 24-70mm', 'price' => 800, 'resale_value' => 0, 'useful_life_years' => 8],
        ],

        'session_tasks' => [
            ['name' => 'Shooting Time', 'hours' => 2],
            ['name' => 'Editing', 'hours' => 3],
            ['name' => 'Client Communication', 'hours' => 1],
        ],

        'monthly_tasks' => [
            ['name' => 'Learning', 'hours' => 10],
            ['name' => 'Marketing', 'hours' => 8],
            ['name' => 'Admin Tasks', 'hours' => 5],
        ],
    ],
];
