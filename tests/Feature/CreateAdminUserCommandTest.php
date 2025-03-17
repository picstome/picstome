<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

test('can create an admin user via the cli', function () {
    $response = artisan('create-admin-user');

    $response->assertSuccessful();
});
