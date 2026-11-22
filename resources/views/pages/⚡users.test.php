<?php

use App\Jobs\PurgeUserAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.custom_storage_limit' => 12,
                'userForm.monthly_contract_limit' => 5,
            ], 'saveUser')
            ->assertHasNoErrors();

        $user->refresh();
        $team = $user->personalTeam();
        expect($team->custom_storage_limit)->toBe(12 * 1024 * 1024 * 1024);
    });

    it('lets an admin update a user\'s monthly invoice limit', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.monthly_invoice_limit' => 10,
            ], 'saveUser')
            ->assertHasNoErrors();

        expect($user->personalTeam()->monthly_invoice_limit)->toBe(10);
    });

    it('lets an admin update a user\'s monthly payment limit', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.monthly_payment_limit' => 3,
            ], 'saveUser')
            ->assertHasNoErrors();

        expect($user->personalTeam()->monthly_payment_limit)->toBe(3);
    });

    it('marks payments as unlimited when the monthly payment limit is empty', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        $team->update(['monthly_payment_limit' => 3]);

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.monthly_payment_limit' => null,
            ], 'saveUser')
            ->assertHasNoErrors();

        expect($team->fresh()->monthly_payment_limit)->toBeNull();
    });

    it('marks invoices as unlimited when the monthly invoice limit is empty', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        $team->update(['monthly_invoice_limit' => 10]);

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.monthly_invoice_limit' => null,
            ], 'saveUser')
            ->assertHasNoErrors();

        expect($team->fresh()->monthly_invoice_limit)->toBeNull();
    });

    it('shows the storage, contracts and invoices limits labels', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->assertSee('Storage Limit')
            ->assertSee('Contracts per month')
            ->assertSee('Invoices per month');
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
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.custom_storage_limit' => null,
                'userForm.monthly_contract_limit' => null,
            ], 'saveUser')
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
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.custom_storage_limit' => 0,
                'userForm.monthly_contract_limit' => 0,
            ], 'saveUser')
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

    it('grants lifetime access and sets lifetime_at', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        expect($team->lifetime_at)->toBeNull();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.lifetime' => true,
            ], 'saveUser')
            ->assertHasNoErrors();

        $team->refresh();
        expect($team->lifetime_at)->not->toBeNull();
    });

    it('revokes lifetime access and clears lifetime_at', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->personalTeam();
        $team->update(['lifetime_at' => now()->subDay()]);
        expect($team->lifetime_at)->not->toBeNull();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->submit([
                'userForm.lifetime' => false,
            ], 'saveUser')
            ->assertHasNoErrors();

        $team->refresh();
        expect($team->lifetime_at)->toBeNull();
    });

    it('filters to show only lifetime users', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $lifetimeUser = User::factory()->withPersonalTeam()->create();
        $lifetimeUser->personalTeam()->update(['lifetime_at' => now()->subDay()]);

        $regularUser = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->set('filter', 'lifetime')
            ->assertSee($lifetimeUser->name)
            ->assertDontSee($regularUser->name);
    });

    it('renders users without a personal team', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $teamlessUser = User::factory()->create();

        expect($teamlessUser->personalTeam())->toBeNull();

        $response = actingAs($admin)->get('/users');

        $response->assertStatus(200);
        $response->assertSee($teamlessUser->email);
    });

    it('lets an admin edit a user without a personal team', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $teamlessUser = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $teamlessUser->id)
            ->assertHasNoErrors();
    });

    it('does not expose connected apps or token revocation in the edit flyout', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->assertDontSee('Connected apps')
            ->assertDontSee('picstome-sync-abc123')
            ->assertDontSee('Revoke');

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    });

    it('offers account deletion from the edit flyout', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create(['email' => 'target@example.com']);

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->assertSee('Danger zone')
            ->assertSee('Delete account');
    });

    it('closes the flyout when the account is deleted', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);
        $user = User::factory()->withPersonalTeam()->create(['email' => 'target@example.com']);

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->call('editUser', $user->id)
            ->dispatch('user-deleted', userId: $user->id)
            ->assertDispatched('modal:edit-user-close');
    });

    it('shows the delete unverified action only in the unverified filter', function () {
        app()->setLocale('es');

        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->assertDontSee('Borrar sin verificar')
            ->set('filter', 'unverified')
            ->assertSee('Borrar sin verificar');
    });

    it('shows how many stale unverified accounts will be deleted', function () {
        app()->setLocale('es');

        config(['picstome.admin_emails' => ['admin@example.com', 'other-admin@example.com']]);

        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
        User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
        User::factory()->unverified()->create([
            'email' => 'other-admin@example.com',
            'created_at' => now()->subDays(8),
        ]);
        User::factory()->create(['created_at' => now()->subDays(30)]);

        $withGallery = User::factory()->unverified()->withPersonalTeam()->create([
            'created_at' => now()->subDays(10),
        ]);
        Gallery::factory()->for($withGallery->personalTeam())->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->submit([
                'filter' => 'unverified',
            ], 'openDeleteUnverifiedModal')
            ->assertSee('Esto elimina permanentemente 1 cuenta vacía sin verificar creada hace más de 7 días.')
            ->assertSee('1 cuenta sin verificar tiene galerías o datos de facturación y no se borrará.')
            ->assertSee('Borrar 1 cuenta')
            ->assertDispatched('modal:delete-unverified-open');
    });

    it('queues a purge for stale unverified accounts and revokes their access', function () {
        Queue::fake();

        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        $eligible = User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
        $eligible->createToken('test-token')->plainTextToken;

        $recent = User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
        $verified = User::factory()->create(['created_at' => now()->subDays(30)]);

        $withGallery = User::factory()->unverified()->withPersonalTeam()->create([
            'created_at' => now()->subDays(10),
        ]);
        Gallery::factory()->for($withGallery->personalTeam())->create();

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->submit([
                'filter' => 'unverified',
            ], 'deleteUnverifiedUsers');

        Queue::assertPushed(PurgeUserAccount::class, 1);
        Queue::assertPushed(PurgeUserAccount::class, fn (PurgeUserAccount $job) => $job->userId === $eligible->id);

        expect($eligible->tokens()->count())->toBe(0)
            ->and(User::find($eligible->id))->not->toBeNull()
            ->and(User::find($recent->id))->not->toBeNull()
            ->and(User::find($verified->id))->not->toBeNull()
            ->and(User::find($withGallery->id))->not->toBeNull();
    });

    it('forbids bulk deletion to non-admins', function () {
        config(['picstome.admin_emails' => []]);

        $user = User::factory()->withPersonalTeam()->create();

        expect($user->is_admin)->toBeFalse();

        Livewire::actingAs($user)
            ->test('pages::users')
            ->call('deleteUnverifiedUsers')
            ->assertStatus(403);
    });

    it('filters monthly and annual paying users separately', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        $monthly = User::factory()->withPersonalTeam()->create(['name' => 'Monthly Payer']);
        $annual = User::factory()->withPersonalTeam()->create(['name' => 'Annual Payer']);
        $lifetime = User::factory()->withPersonalTeam()->create(['name' => 'Lifetime Payer']);
        $lifetime->personalTeam()->update(['lifetime_at' => now()->subDay()]);
        $canceled = User::factory()->withPersonalTeam()->create(['name' => 'Canceled Payer']);

        foreach ([[$monthly, 'month', 'active'], [$annual, 'year', 'active'], [$canceled, 'month', 'canceled']] as [$user, $interval, $status]) {
            DB::table('subscriptions')->insert([
                'team_id' => $user->personalTeam()->id,
                'type' => 'default',
                'stripe_id' => 'sub_'.uniqid(),
                'stripe_status' => $status,
                'stripe_price' => 'price_'.$interval,
                'quantity' => 1,
                'billing_interval' => $interval,
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->set('filter', 'monthly')
            ->assertSee('Monthly Payer')
            ->assertDontSee('Annual Payer')
            ->assertDontSee('Lifetime Payer')
            ->assertDontSee('Canceled Payer');

        Livewire::actingAs($admin)
            ->test('pages::users')
            ->set('filter', 'annual')
            ->assertSee('Annual Payer')
            ->assertDontSee('Monthly Payer')
            ->assertDontSee('Lifetime Payer')
            ->assertDontSee('Canceled Payer');
    });

    it('exports only the filtered users', function () {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@example.com',
        ]);

        $monthly = User::factory()->withPersonalTeam()->create([
            'name' => 'Monthly Export',
            'email' => 'monthly-export@example.com',
        ]);
        $annual = User::factory()->withPersonalTeam()->create([
            'name' => 'Annual Export',
            'email' => 'annual-export@example.com',
        ]);

        foreach ([[$monthly, 'month'], [$annual, 'year']] as [$user, $interval]) {
            DB::table('subscriptions')->insert([
                'team_id' => $user->personalTeam()->id,
                'type' => 'default',
                'stripe_id' => 'sub_'.uniqid(),
                'stripe_status' => 'active',
                'stripe_price' => 'price_'.$interval,
                'quantity' => 1,
                'billing_interval' => $interval,
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $component = Livewire::actingAs($admin)
            ->test('pages::users')
            ->set('filter', 'monthly');

        $response = $component->instance()->exportCsv();

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        expect($csv)
            ->toContain('monthly-export@example.com')
            ->not->toContain('annual-export@example.com');
    });
});

it('shows the email verification state in the browser', function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $user = User::factory()->withPersonalTeam()->unverified()->create([
        'email' => 'pending@example.com',
    ]);

    actingAs($admin);

    $page = visit('/users');

    $page->assertSee('Email status')
        ->assertSee('Unverified');

    $page->click('@edit-user-'.$user->id);

    $page->assertSee('Email verified')
        ->assertSee('Mark the address as verified without sending the confirmation email.');
})->group('browser');
