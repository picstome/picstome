<?php

use App\Jobs\PurgeUserAccount;
use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create([
        'email' => 'owner@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $team = $this->user->personalTeam();

    $gallery = Gallery::factory()->for($team)->create();
    Photo::factory()->for($gallery)->create(['size' => 2048, 'stored_size' => 2048]);
    Invoice::factory()->for($team)->issued()->create();
});

it('shows what the account holds', function () {
    Livewire::actingAs($this->user)
        ->test('delete-account-modal')
        ->assertSee('Delete account')
        ->assertSee('Galleries')
        ->assertSee('Photos and videos')
        ->assertSee('Contracts')
        ->assertSee('Invoices')
        ->assertSee('Customers')
        ->assertSee('issued invoice', false);
});

it('warns that the subscription is cancelled without a refund', function () {
    $team = $this->user->personalTeam();

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_fake',
        'stripe_status' => 'active',
        'ends_at' => now()->addDays(12),
    ]);

    Livewire::actingAs($this->user)
        ->test('delete-account-modal')
        ->assertSee('cancelled immediately', false)
        ->assertSee('not refunded', false);
});

it('requires the current password', function () {
    Queue::fake();

    Livewire::actingAs($this->user)
        ->test('delete-account-modal')
        ->set('password', 'wrong-password')
        ->set('confirmation', 'owner@example.com')
        ->call('delete')
        ->assertHasErrors(['password']);

    expect(User::query()->whereKey($this->user->id)->exists())->toBeTrue();
    Queue::assertNothingPushed();
});

it('requires typing the account email', function () {
    Queue::fake();

    Livewire::actingAs($this->user)
        ->test('delete-account-modal')
        ->set('password', 'secret-password')
        ->set('confirmation', 'someone-else@example.com')
        ->call('delete')
        ->assertHasErrors(['confirmation']);

    expect(User::query()->whereKey($this->user->id)->exists())->toBeTrue();
    Queue::assertNothingPushed();
});

it('deletes the account, logs out and queues the purge', function () {
    Queue::fake();

    $token = $this->user->createToken('picstome-sync-abc123', ['sync']);

    Livewire::actingAs($this->user)
        ->test('delete-account-modal')
        ->set('password', 'secret-password')
        ->set('confirmation', 'OWNER@example.com')
        ->call('delete')
        ->assertRedirect(route('login'));

    Queue::assertPushed(PurgeUserAccount::class, fn (PurgeUserAccount $job) => $job->userId === $this->user->id);

    expect(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
    expect(session('status'))->toBe(__('Your account is being deleted. You have been signed out.'));
});

it('throttles password guessing', function () {
    Livewire::actingAs($this->user);

    foreach (range(1, 5) as $attempt) {
        Livewire::test('delete-account-modal')
            ->set('password', 'wrong-password')
            ->set('confirmation', 'owner@example.com')
            ->call('delete');
    }

    Livewire::test('delete-account-modal')
        ->set('password', 'secret-password')
        ->set('confirmation', 'owner@example.com')
        ->call('delete')
        ->assertHasErrors(['password']);
});

it('lets an admin delete another account with the typed email', function () {
    Queue::fake();

    config(['picstome.admin_emails' => ['admin@example.com']]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $target = User::factory()->withPersonalTeam()->create(['email' => 'target@example.com']);

    Livewire::actingAs($admin)
        ->test('delete-account-modal', ['user' => $target])
        ->assertSee('target@example.com')
        ->assertDontSee('Your current password')
        ->set('confirmation', 'target@example.com')
        ->call('delete')
        ->assertDispatched('user-deleted', userId: $target->id);

    Queue::assertPushed(PurgeUserAccount::class, fn (PurgeUserAccount $job) => $job->userId === $target->id);
    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

it('rejects a mismatched email in admin mode', function () {
    Queue::fake();

    config(['picstome.admin_emails' => ['admin@example.com']]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $target = User::factory()->withPersonalTeam()->create(['email' => 'target@example.com']);

    Livewire::actingAs($admin)
        ->test('delete-account-modal', ['user' => $target])
        ->set('confirmation', 'wrong@example.com')
        ->call('delete')
        ->assertHasErrors(['confirmation']);

    Queue::assertNothingPushed();
});

it('forbids a non admin from deleting another account', function () {
    $this->withoutExceptionHandling();

    $user = User::factory()->withPersonalTeam()->create();
    $target = User::factory()->withPersonalTeam()->create();

    expect(fn () => Livewire::actingAs($user)->test('delete-account-modal', ['user' => $target]))
        ->toThrow(HttpException::class);
});

it('never deletes the acting admin through admin mode', function () {
    Queue::fake();

    config(['picstome.admin_emails' => ['admin@example.com']]);

    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    // Passing yourself keeps the owner path: the password is required.
    Livewire::actingAs($admin)
        ->test('delete-account-modal', ['user' => $admin])
        ->set('confirmation', 'admin@example.com')
        ->call('delete')
        ->assertHasErrors(['password']);

    Queue::assertNothingPushed();
});

it('is reachable from the profile and password settings pages', function () {
    $this->actingAs($this->user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertSee('Delete account');

    $this->actingAs($this->user)
        ->get('/settings/password')
        ->assertOk()
        ->assertSee('Delete account');
});

it('exposes the danger zone in the admin user flyout', function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $target = User::factory()->withPersonalTeam()->create(['email' => 'target@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::users')
        ->call('editUser', $target->id)
        ->assertSee('Danger zone')
        ->assertSee('Delete account');
});
