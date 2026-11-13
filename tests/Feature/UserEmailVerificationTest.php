<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $this->admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
});

it('shows the email verification state in the users table', function () {
    User::factory()->withPersonalTeam()->create([
        'name' => 'Verified Person',
        'email' => 'verified@example.com',
    ]);
    User::factory()->withPersonalTeam()->unverified()->create([
        'name' => 'Pending Person',
        'email' => 'pending@example.com',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->assertSee('Email status')
        ->assertSee('Verified')
        ->assertSee('Unverified');
});

it('filters users by email verification', function () {
    User::factory()->withPersonalTeam()->create(['name' => 'Verified Person']);
    User::factory()->withPersonalTeam()->unverified()->create(['name' => 'Pending Person']);

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->set('filter', 'unverified')
        ->assertSee('Pending Person')
        ->assertDontSee('Verified Person');

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->set('filter', 'verified')
        ->assertSee('Verified Person')
        ->assertDontSee('Pending Person');
});

it('lets an admin mark the email as verified or unverified', function () {
    $user = User::factory()->withPersonalTeam()->unverified()->create();

    expect($user->hasVerifiedEmail())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->call('editUser', $user->id)
        ->assertSet('userForm.emailVerified', false)
        ->set('userForm.emailVerified', true)
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertSee('Verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->call('editUser', $user->id)
        ->assertSet('userForm.emailVerified', true)
        ->set('userForm.emailVerified', false)
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('saves verification of users without a team', function () {
    $user = User::factory()->unverified()->create();

    expect($user->personalTeam())->toBeNull();

    Livewire::actingAs($this->admin)
        ->test('pages::users')
        ->call('editUser', $user->id)
        ->set('userForm.emailVerified', true)
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
