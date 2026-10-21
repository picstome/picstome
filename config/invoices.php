<?php

return [

    /**
     * Default invoicing values applied to a team when the settings are
     * created for the first time.
     */
    'defaults' => [
        'series' => 'F',
        'rectification_series' => 'R',
        'currency' => 'EUR',
        'timezone' => 'Europe/Madrid',
        'vat_percent' => 21,
        'irpf_percent' => 15,
        'apply_irpf' => false,
        'simplified_invoice_limit' => 3000,
    ],

    /**
     * VAT rates that can be applied to an invoice.
     *
     * @var list<float>
     */
    'vat_rates' => [21, 10, 4, 0],

    /**
     * Currencies an invoice can be issued in.
     *
     * @var array<string, string>
     */
    'currencies' => [
        'EUR' => 'EUR — Euro',
        'USD' => 'USD — US Dollar',
        'GBP' => 'GBP — British Pound',
        'CHF' => 'CHF — Swiss Franc',
        'CAD' => 'CAD — Canadian Dollar',
        'AUD' => 'AUD — Australian Dollar',
        'BRL' => 'BRL — Brazilian Real',
        'MXN' => 'MXN — Mexican Peso',
        'ARS' => 'ARS — Argentine Peso',
        'CLP' => 'CLP — Chilean Peso',
        'COP' => 'COP — Colombian Peso',
        'PEN' => 'PEN — Peruvian Sol',
        'UYU' => 'UYU — Uruguayan Peso',
        'DOP' => 'DOP — Dominican Peso',
        'CRC' => 'CRC — Costa Rican Colón',
        'GTQ' => 'GTQ — Guatemalan Quetzal',
        'JPY' => 'JPY — Japanese Yen',
    ],

    /**
     * Accent colors used on the invoice PDF, mapped from the Tailwind name
     * stored in the team branding settings.
     *
     * @var array<string, string>
     */
    'colors' => [
        'red' => '#ef4444',
        'orange' => '#f97316',
        'amber' => '#f59e0b',
        'yellow' => '#eab308',
        'lime' => '#84cc16',
        'green' => '#22c55e',
        'emerald' => '#10b981',
        'teal' => '#14b8a6',
        'cyan' => '#06b6d4',
        'sky' => '#0ea5e9',
        'blue' => '#3b82f6',
        'indigo' => '#6366f1',
        'violet' => '#8b5cf6',
        'purple' => '#a855f7',
        'fuchsia' => '#d946ef',
        'pink' => '#ec4899',
        'rose' => '#f43f5e',
        'zinc' => '#71717a',
    ],

    /**
     * Fallback accent color when the team has no brand color.
     */
    'fallback_color' => '#16a34a',

    /**
     * QR code rendering.
     */
    'qr' => [
        'size' => 300,
        'margin' => 0,
    ],

    /**
     * Number of decimals used on the invoice amounts.
     */
    'decimals' => 2,

];
