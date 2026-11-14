<?php

return [

    'currency' => 'EUR',

    /**
     * Subscription tiers shown on the pricing page.
     *
     * Stripe remains the source of truth for the actual prices. The amounts
     * here are the display values (in cents) so the page can render without
     * calling Stripe on every request. `lookup_keys` resolve the Stripe price
     * ID for the current mode (test or live) at checkout time.
     *
     * @var array<string, array{
     *     name: string,
     *     photos: string,
     *     storage_limit: int,
     *     prices: array{monthly: int, annual: int, lifetime: int},
     *     price_ids: array{monthly: string|null, annual: string|null, lifetime: string|null},
     *     lookup_keys: array{monthly: string, annual: string, lifetime: string},
     *     highlighted: bool
     * }>
     */
    'tiers' => [
        '100gb' => [
            'name' => '100 GB',
            'stripe_product_name' => '100GB',
            'photos' => '≈ 200.000 fotos',
            'storage_limit' => 107374182400,
            'prices' => [
                'monthly' => 700,
                'annual' => 6000,
                'lifetime' => 17900,
            ],
            'price_ids' => [
                'monthly' => env('STRIPE_100GB_MONTHLY_PRICE'),
                'annual' => env('STRIPE_100GB_ANNUAL_PRICE'),
                'lifetime' => env('STRIPE_100GB_LIFETIME_PRICE'),
            ],
            'lookup_keys' => [
                'monthly' => 'picstome_100gb_monthly',
                'annual' => 'picstome_100gb_annual',
                'lifetime' => 'picstome_100gb_lifetime',
            ],
            'highlighted' => false,
        ],
        '250gb' => [
            'name' => '250 GB',
            'stripe_product_name' => '250GB',
            'photos' => '≈ 500.000 fotos',
            'storage_limit' => 268435456000,
            'prices' => [
                'monthly' => 1500,
                'annual' => 12000,
                'lifetime' => 35900,
            ],
            'price_ids' => [
                'monthly' => env('STRIPE_250GB_MONTHLY_PRICE'),
                'annual' => env('STRIPE_250GB_ANNUAL_PRICE'),
                'lifetime' => env('STRIPE_250GB_LIFETIME_PRICE'),
            ],
            'lookup_keys' => [
                'monthly' => 'picstome_250gb_monthly',
                'annual' => 'picstome_250gb_annual',
                'lifetime' => 'picstome_250gb_lifetime',
            ],
            'highlighted' => true,
        ],
        '1tb' => [
            'name' => '1 TB',
            'stripe_product_name' => '1000GB',
            'photos' => '≈ 2.000.000 fotos',
            'storage_limit' => 1073741824000,
            'prices' => [
                'monthly' => 3000,
                'annual' => 24000,
                'lifetime' => 69900,
            ],
            'price_ids' => [
                'monthly' => env('STRIPE_1TB_MONTHLY_PRICE'),
                'annual' => env('STRIPE_1TB_ANNUAL_PRICE'),
                'lifetime' => env('STRIPE_1TB_LIFETIME_PRICE'),
            ],
            'lookup_keys' => [
                'monthly' => 'picstome_1tb_monthly',
                'annual' => 'picstome_1tb_annual',
                'lifetime' => 'picstome_1tb_lifetime',
            ],
            'highlighted' => false,
        ],
    ],

    /**
     * Benefits shown above the plan grid. The free plan already includes every
     * feature; these rows explain what changes when the team upgrades.
     *
     * @var list<array{icon: string, title: string, description: string, from: string, to: string}>
     */
    'benefits' => [
        [
            'icon' => 'cloud-arrow-up',
            'title' => 'Up to 1 TB storage',
            'description' => '1000× more space than the free plan.',
            'from' => '1 GB',
            'to' => '1 TB',
        ],
        [
            'icon' => 'clock',
            'title' => 'Galleries that never expire',
            'description' => 'Keep every gallery, and remove its expiration date.',
            'from' => '1 year',
            'to' => 'Never',
        ],
        [
            'icon' => 'document-text',
            'title' => 'No monthly limits',
            'description' => 'Unlimited invoices, contracts and payments.',
            'from' => '1',
            'to' => 'Unlimited',
        ],
        [
            'icon' => 'paint-brush',
            'title' => 'White label',
            'description' => 'No Picstome branding on galleries and documents.',
            'from' => 'Picstome',
            'to' => 'Your brand',
        ],
    ],

];
