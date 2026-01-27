<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests to login page', function () {
    get('/subscribe')->assertRedirect('login');
});
