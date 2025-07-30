<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests to login page', function () {
    get('billing-portal')->assertRedirect('login');
});

it('redirects users without subscription to the subscribe page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)->get('billing-portal')->assertRedirect('subscribe');
});
