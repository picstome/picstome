<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Fake Stripe's HTTP client so the subscribe page can be rendered without
 * creating customers or customer sessions in the real Stripe account.
 */
function fakeStripeHttpClient(): void
{
    ApiRequestor::setHttpClient(new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $payload = str_contains($absUrl, 'customer_sessions')
                ? ['id' => 'cuss_test', 'object' => 'customer_session', 'client_secret' => 'cuss_secret_test']
                : ['id' => 'cus_test', 'object' => 'customer'];

            return [json_encode($payload), 200, []];
        }
    });
}

beforeEach(function () {
    config([
        'cashier.key' => 'pk_test_fake',
        'cashier.secret' => 'sk_test_fake',
        'services.stripe.en_pricing_table_id' => 'prctbl_test_en',
        'services.stripe.es_pricing_table_id' => 'prctbl_test_es',
    ]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function subscribePageUser(array $teamAttributes = []): User
{
    $user = User::factory()->create();

    $team = Team::factory()->create([
        'user_id' => $user->id,
        'personal_team' => true,
        ...$teamAttributes,
    ]);

    $user->update(['current_team_id' => $team->id]);

    return $user->fresh();
}

it('redirects guests to login page', function () {
    get('/subscribe')->assertRedirect('login');
});

it('redirects subscribed teams to the dashboard', function () {
    $user = subscribePageUser(['lifetime_at' => now()]);

    actingAs($user)
        ->get('/subscribe')
        ->assertRedirect(route('dashboard'));
});

it('shows the pro benefits and the paid plan badge', function () {
    fakeStripeHttpClient();

    $user = subscribePageUser();

    actingAs($user)
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Paid plan')
        ->assertSee('Unlimited invoices')
        ->assertSee('Unlimited contracts')
        ->assertSee('Up to 1 TB storage')
        ->assertSee('Secure payment with Stripe')
        ->assertSee('Everything in the free plan stays included.');
});

it('uses the pricing table for the current locale', function () {
    fakeStripeHttpClient();

    $user = subscribePageUser();
    $user->update(['language' => 'es']);

    actingAs($user)
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Facturas ilimitadas')
        ->assertSee('prctbl_test_es')
        ->assertDontSee('prctbl_test_en');
});
