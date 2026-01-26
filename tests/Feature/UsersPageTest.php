<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('Users Page', function () {
    beforeEach(function () {
        // No global setup needed for all tests, but can be added if required
    });

    it('allows an admin to view the users page', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        $response = actingAs($admin)->get('/users');

        $response->assertStatus(200);
    });

    it('forbids access to the users page for non-admins', function () {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'user@example.com',
        ]);

        $response = actingAs($user)->get('/users');

        $response->assertStatus(403);
    });

    it('lets an admin update a user\'s custom storage limit', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test('pages.users')
            ->call('editUser', $user->id)
            ->set('userForm.custom_storage_limit', 12)
            ->set('userForm.monthly_contract_limit', 5)
            ->call('saveUser')
            ->assertHasNoErrors();

        $user->refresh();
        $team = $user->personalTeam();
        expect($team->custom_storage_limit)->toBe(12 * 1024 * 1024 * 1024);
    });

    it('marks storage as unlimited when the custom storage limit is set to null', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        $team->update(['custom_storage_limit' => 123456789]);
        expect($team->has_unlimited_storage)->toBeFalse();

        Livewire::actingAs($admin)
            ->test('pages.users')
            ->call('editUser', $user->id)
            ->set('userForm.custom_storage_limit', null)
            ->set('userForm.monthly_contract_limit', null)
            ->call('saveUser')
            ->assertHasNoErrors();

        $team->refresh();
        expect($team->has_unlimited_storage)->toBeTrue();
    });

    it('sets the custom storage limit to zero when the admin enters zero', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        $team->update(['custom_storage_limit' => 123456789]);

        Livewire::actingAs($admin)
            ->test('pages.users')
            ->call('editUser', $user->id)
            ->set('userForm.custom_storage_limit', 0)
            ->set('userForm.monthly_contract_limit', 0)
            ->call('saveUser')
            ->assertHasNoErrors();

        $team->refresh();
        expect($team->custom_storage_limit)->toBe(0);
    });

    it('always shows storage used percent as 100 when storage limit is zero', function () {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();

        $team->update([
            'custom_storage_limit' => 0,
        ]);
        expect($team->storage_used_percent)->toBe(100);

        Photo::factory()->for(
            Gallery::factory()->for($team)
        )->create(['size' => 12345]);

        expect($team->storage_used_percent)->toBe(100);
    });
});
