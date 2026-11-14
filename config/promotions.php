<?php

return [

    'black_friday' => [

        'enabled' => (bool) env('BLACK_FRIDAY_ENABLED', false),

        /**
         * Local/testing escape hatch. It lets the team preview the campaign
         * before the public start date. It is ignored outside local/testing.
         */
        'force' => (bool) env('BLACK_FRIDAY_FORCE', false),

        'starts_at' => env('BLACK_FRIDAY_STARTS_AT', '2026-11-16 00:00:00'),
        'ends_at' => env('BLACK_FRIDAY_ENDS_AT', '2026-12-01 00:00:00'),
        'timezone' => env('BLACK_FRIDAY_TIMEZONE', 'Europe/Madrid'),

        // Applies to the first annual year and to the one-time plan.
        'annual_discount_percent' => 20,

        /**
         * First month price in cents for the monthly plans. The 1 TB tier is
         * deliberately 5 € instead of 1 € so the campaign still covers the
         * storage cost.
         *
         * @var array<string, int>
         */
        'first_month_prices' => [
            '100gb' => 100,
            '250gb' => 100,
            '1tb' => 500,
        ],

        /**
         * Coupon IDs are created in Stripe and configured per environment.
         * They are applied from the backend; no public promotion code is
         * exposed. The annual/one-time coupon has no redeem_by because monthly
         * teams receive it on their next annual invoice.
         *
         * @var array{monthly: array<string, string|null>, annual: string|null}
         */
        'coupons' => [
            'monthly' => [
                '100gb' => env('BLACK_FRIDAY_COUPON_100GB'),
                '250gb' => env('BLACK_FRIDAY_COUPON_250GB'),
                '1tb' => env('BLACK_FRIDAY_COUPON_1TB'),
            ],
            'annual' => env('BLACK_FRIDAY_COUPON_ANNUAL'),
        ],

    ],

];
