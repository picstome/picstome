<?php

use Illuminate\Support\Facades\Blade;

it('renders a copy button for copyable inputs', function () {
    $html = Blade::render('<x-ts-input value="https://example.test" readonly copyable />');

    expect($html)
        ->toContain('data-copyable')
        ->not->toContain('copyable=');
});
