<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Help center
    |--------------------------------------------------------------------------
    |
    | The help center is a static site published from the docs repository. The
    | app links to it in a new tab and never calls it at runtime.
    |
    | The route map below resolves the current page to the article that explains
    | it. Keys are Laravel route names; values are paths inside the docs site,
    | without the locale prefix or the trailing slash (HelpLink adds both).
    |
    | Routes that are not listed here fall back to the help center home, so a
    | new screen never produces a dead link.
    |
    */

    'url' => env('PICSTOME_HELP_URL', 'https://docs.picstome.com'),

    'routes' => [
        'dashboard' => 'start/what-picstome-does',
        'galleries' => 'start/first-gallery',
        'galleries.show' => 'sharing',
        'galleries.photos.show' => 'culling/rating',

        'customers' => 'business/clients',
        'customers.show' => 'business/clients',
        'clients.show' => 'business/clients',

        'contracts' => 'business/contracts',
        'contracts.show' => 'business/contracts',
        'contract-templates' => 'business/contracts',
        'contract-templates.show' => 'business/contracts',

        'invoices' => 'money/invoices',
        'invoices.create' => 'money/invoices',
        'invoices.show' => 'money/invoices',
        'invoices.settings' => 'money/vat',

        'payments' => 'money/stripe',
        'stripe.connect' => 'money/stripe',

        'tools.calculator' => 'apps/calculator',
        'tools.black-screen' => 'apps/black-screen',

        'public-profile' => 'brand/public-profile',
        'portfolio' => 'brand/portfolio',
        'branding' => 'brand/public-profile',
        'branding.general' => 'brand/public-profile',
        'branding.styling' => 'brand/public-profile',
        'branding.watermark' => 'brand/watermark',
        'branding.payments' => 'money/stripe',

        'subscribe' => 'start/plans-and-storage',
    ],

];
