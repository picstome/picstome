<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns the profile photo url when the user uploaded one', function () {
    Storage::fake(config('picstome.disk', 'public'));
    $user = User::factory()->withPersonalTeam()->create();
    $user->updateAvatar(UploadedFile::fake()->image('me.jpg'));
    Sanctum::actingAs($user, ['sync']);

    $response = $this->getJson('/api/v1/me')->assertOk();

    expect($response->json('avatar_url'))
        ->toBeString()
        ->toBe($user->fresh()->avatar_url);
});

it('returns no avatar url for the generated initials avatar', function () {
    Sanctum::actingAs(User::factory()->withPersonalTeam()->create(), ['sync']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('avatar_url', null);
});

it('returns the plan and limits of a free team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $team->update([
        'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
        'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
        'monthly_invoice_limit' => config('picstome.personal_team_monthly_invoice_limit'),
        'monthly_payment_limit' => config('picstome.personal_team_monthly_payment_limit'),
    ]);

    Contract::factory()->for($team)->create();
    Invoice::factory()->for($team)->issued()->create();
    Payment::factory()->for($team)->create();

    Sanctum::actingAs($user, ['sync']);

    $response = $this->getJson('/api/v1/me')->assertOk();

    $response->assertJsonPath('team.id', $team->id)
        ->assertJsonPath('plan.key', 'free')
        ->assertJsonPath('plan.name', 'Free')
        ->assertJsonPath('plan.is_free', true)
        ->assertJsonPath('plan.is_lifetime', false)
        ->assertJsonPath('plan.billing_interval', null)
        ->assertJsonPath('plan.renews_at', null)
        ->assertJsonPath('limits.storage.limit_bytes', config('picstome.personal_team_storage_limit'))
        ->assertJsonPath('limits.storage.unlimited', false)
        ->assertJsonPath('limits.gallery_retention.unlimited', false)
        ->assertJsonPath('limits.gallery_retention.max_years', 1)
        ->assertJsonPath('limits.gallery_retention.default_expiration_months', 1)
        ->assertJsonPath('limits.contracts_per_month.limit', 1)
        ->assertJsonPath('limits.contracts_per_month.used', 1)
        ->assertJsonPath('limits.contracts_per_month.remaining', 0)
        ->assertJsonPath('limits.contracts_per_month.unlimited', false)
        ->assertJsonPath('limits.invoices_per_month.limit', 1)
        ->assertJsonPath('limits.invoices_per_month.used', 1)
        ->assertJsonPath('limits.invoices_per_month.remaining', 0)
        ->assertJsonPath('limits.payments_per_month.limit', 1)
        ->assertJsonPath('limits.payments_per_month.used', 1)
        ->assertJsonPath('limits.payments_per_month.remaining', 0);

    expect($response->json('limits.storage.used_bytes'))->toBeInt()
        ->and($response->json('limits.storage.remaining_bytes'))->toBeInt();
});

it('returns unlimited limits for an active subscription', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();
    $storageLimit = config('plans.tiers.250gb.storage_limit');

    $team->update([
        'custom_storage_limit' => $storageLimit,
        'monthly_contract_limit' => 1,
        'monthly_invoice_limit' => 1,
        'monthly_payment_limit' => 1,
    ]);

    Subscription::factory()->for($team, 'owner')->create([
        'billing_interval' => 'month',
        'current_period_end' => now()->addMonth(),
    ]);

    Sanctum::actingAs($user, ['sync']);

    $response = $this->getJson('/api/v1/me')->assertOk();

    $response->assertJsonPath('plan.key', '250gb')
        ->assertJsonPath('plan.name', '250 GB')
        ->assertJsonPath('plan.is_free', false)
        ->assertJsonPath('plan.is_lifetime', false)
        ->assertJsonPath('plan.billing_interval', 'month')
        ->assertJsonPath('limits.storage.limit_bytes', $storageLimit)
        ->assertJsonPath('limits.storage.unlimited', false)
        ->assertJsonPath('limits.gallery_retention.unlimited', true)
        ->assertJsonPath('limits.contracts_per_month.limit', null)
        ->assertJsonPath('limits.contracts_per_month.unlimited', true)
        ->assertJsonPath('limits.invoices_per_month.limit', null)
        ->assertJsonPath('limits.invoices_per_month.unlimited', true)
        ->assertJsonPath('limits.payments_per_month.limit', null)
        ->assertJsonPath('limits.payments_per_month.unlimited', true);

    expect($response->json('plan.renews_at'))->toBeString()->not->toBeEmpty();
});

it('reports lifetime plans without unlimited retention', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $team->update([
        'custom_storage_limit' => config('plans.tiers.250gb.storage_limit'),
        'lifetime_at' => now(),
        'monthly_contract_limit' => 1,
        'monthly_invoice_limit' => 1,
        'monthly_payment_limit' => 1,
    ]);

    Sanctum::actingAs($user, ['sync']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('plan.key', '250gb')
        ->assertJsonPath('plan.is_free', false)
        ->assertJsonPath('plan.is_lifetime', true)
        ->assertJsonPath('plan.billing_interval', null)
        ->assertJsonPath('plan.renews_at', null)
        ->assertJsonPath('limits.gallery_retention.unlimited', false)
        ->assertJsonPath('limits.contracts_per_month.limit', null)
        ->assertJsonPath('limits.contracts_per_month.unlimited', true)
        ->assertJsonPath('limits.invoices_per_month.unlimited', true)
        ->assertJsonPath('limits.payments_per_month.unlimited', true);
});
