<?php

use App\Jobs\SendGa4Purchase;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AttributionService;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function attributionRequest(array $query = [], array $cookies = [], array $server = []): Request
{
    $uri = '/register'.($query !== [] ? '?'.http_build_query($query) : '');

    return Request::create($uri, 'GET', [], $cookies, [], $server);
}

it('uses the first touch from the cookie', function () {
    $cookie = json_encode([
        'v' => 1,
        'first' => [
            'utm_source' => 'qa',
            'utm_medium' => 'test',
            'utm_campaign' => 'prueba',
            'ts' => 1700000000000,
        ],
        'last' => [
            'utm_source' => 'meta',
            'fbclid' => 'click-123',
        ],
    ]);

    $attribution = app(AttributionService::class)->capture(
        attributionRequest(cookies: ['ps_attr' => $cookie])
    );

    expect($attribution['source'])->toBe('qa')
        ->and($attribution['medium'])->toBe('test')
        ->and($attribution['campaign'])->toBe('prueba')
        ->and($attribution['raw']['last']['fbclid'])->toBe('click-123');
});

it('prefers the cookie over ps_aid', function () {
    $cookie = json_encode(['v' => 1, 'first' => ['utm_source' => 'cookie', 'utm_medium' => 'test']]);
    $aid = base64_encode(json_encode(['s' => 'link', 'm' => 'test']));

    $attribution = app(AttributionService::class)->capture(
        attributionRequest(query: ['ps_aid' => $aid], cookies: ['ps_attr' => $cookie])
    );

    expect($attribution['source'])->toBe('cookie');
});

it('reads ps_aid when there is no cookie', function () {
    $aid = base64_encode(json_encode([
        's' => 'qa',
        'm' => 'test',
        'c' => 'prueba',
        'l' => 'https://picstome.com/pricing/',
        'ts' => 1700000000,
    ]));

    $attribution = app(AttributionService::class)->capture(
        attributionRequest(query: ['ps_aid' => $aid])
    );

    expect($attribution['source'])->toBe('qa')
        ->and($attribution['medium'])->toBe('test')
        ->and($attribution['landing'])->toBe('https://picstome.com/pricing/');
});

it('falls back to the current utm parameters', function () {
    $attribution = app(AttributionService::class)->capture(
        attributionRequest(query: [
            'utm_source' => 'qa',
            'utm_medium' => 'test',
            'utm_campaign' => 'prueba',
        ])
    );

    expect($attribution['source'])->toBe('qa')
        ->and($attribution['medium'])->toBe('test')
        ->and($attribution['campaign'])->toBe('prueba');
});

it('classifies google organic traffic from the referrer', function () {
    $attribution = app(AttributionService::class)->capture(
        attributionRequest(server: ['HTTP_REFERER' => 'https://www.google.com/search?q=picstome'])
    );

    expect($attribution['source'])->toBe('google')
        ->and($attribution['medium'])->toBe('organic');
});

it('treats referrers from picstome as direct', function () {
    $attribution = app(AttributionService::class)->capture(
        attributionRequest(server: ['HTTP_REFERER' => 'https://picstome.com/pricing/'])
    );

    expect($attribution)->toBeNull();
});

it('classifies social and referral hosts without false positives', function () {
    $facebook = app(AttributionService::class)->capture(
        attributionRequest(server: ['HTTP_REFERER' => 'https://l.facebook.com/l.php'])
    );
    $netflix = app(AttributionService::class)->capture(
        attributionRequest(server: ['HTTP_REFERER' => 'https://www.netflix.com/browse'])
    );

    expect($facebook['source'])->toBe('facebook')
        ->and($facebook['medium'])->toBe('social')
        ->and($netflix['source'])->toBe('netflix.com')
        ->and($netflix['medium'])->toBe('referral');
});

it('classifies click ids without utms', function () {
    $google = app(AttributionService::class)->capture(attributionRequest(query: ['gclid' => 'abc']));
    $meta = app(AttributionService::class)->capture(attributionRequest(query: ['fbclid' => 'abc']));

    expect($google['source'])->toBe('google')
        ->and($google['medium'])->toBe('cpc')
        ->and($meta['source'])->toBe('facebook')
        ->and($meta['medium'])->toBe('paid');
});

it('ignores malformed cookies and link parameters', function () {
    expect(app(AttributionService::class)->capture(
        attributionRequest(cookies: ['ps_attr' => '{broken'])
    ))->toBeNull();

    expect(app(AttributionService::class)->capture(
        attributionRequest(query: ['ps_aid' => 'not-base64'])
    ))->toBeNull();
});

it('stores the attribution on the user', function () {
    $request = attributionRequest(cookies: ['ps_attr' => json_encode([
        'v' => 1,
        'first' => [
            's' => 'qa',
            'm' => 'test',
            'c' => 'prueba',
            'l' => 'https://picstome.com/pricing/',
            'r' => 'https://www.google.com/',
            'ts' => 1700000000,
        ],
    ])]);
    $request->setLaravelSession(app('session')->driver());

    $user = User::factory()->create();

    app(AttributionService::class)->persist($user, $request);

    expect($user->fresh()->attr_source)->toBe('qa')
        ->and($user->fresh()->attr_medium)->toBe('test')
        ->and($user->fresh()->attr_campaign)->toBe('prueba')
        ->and($user->fresh()->attr_landing)->toBe('https://picstome.com/pricing/')
        ->and($user->fresh()->attr_json['normalized']['source'])->toBe('qa');
});

it('captures the attribution during registration', function () {
    $this->mock(AttributionService::class, function ($mock): void {
        $mock->shouldReceive('remember')->once();
        $mock->shouldReceive('persist')->once();
    });

    Livewire::test('pages::register')
        ->submit([
            'name' => 'Wired User',
            'email' => 'wired@example.com',
            'terms' => true,
        ], 'register')
        ->assertDispatched('analytics:event');
});

it('falls back to direct when there is no attribution', function () {
    Livewire::test('pages::register')
        ->submit([
            'name' => 'Direct User',
            'email' => 'direct@example.com',
            'terms' => true,
        ], 'register');

    $user = User::where('email', 'direct@example.com')->first();

    expect($user->attr_source)->toBe('direct')
        ->and($user->attr_medium)->toBeNull();
});

it('never overwrites an attribution already stored on the user', function () {
    $user = User::factory()->create();
    $user->forceFill(['attr_source' => 'original'])->save();

    app(AttributionService::class)->persist($user->fresh(), attributionRequest(query: [
        'utm_source' => 'new',
    ]));

    expect($user->fresh()->attr_source)->toBe('original');
});

it('keeps the ga client id for the server-side purchase', function () {
    $request = attributionRequest(cookies: ['_ga' => 'GA1.1.123456789.1699999999']);
    $request->setLaravelSession(app('session')->driver());

    $user = User::factory()->create();

    app(AttributionService::class)->persist($user, $request);

    expect($user->fresh()->attr_json['ga_client_id'])->toBe('123456789.1699999999');
});

it('does not load ga4 without consent', function () {
    config([
        'analytics.enabled' => true,
        'analytics.ga4.measurement_id' => 'G-TEST',
    ]);

    get('/register')
        ->assertOk()
        ->assertDontSee('G-TEST');
});

it('loads ga4 for consented visitors', function () {
    config([
        'analytics.enabled' => true,
        'analytics.ga4.measurement_id' => 'G-TEST',
    ]);

    $this->withUnencryptedCookie('ps_consent', json_encode(['analytics' => true]))
        ->get('/register')
        ->assertOk()
        ->assertSee('G-TEST');
});

it('does not load ga4 when analytics is disabled', function () {
    config([
        'analytics.enabled' => false,
        'analytics.ga4.measurement_id' => 'G-TEST',
    ]);

    $this->withUnencryptedCookie('ps_consent', json_encode(['analytics' => true]))
        ->get('/register')
        ->assertOk()
        ->assertDontSee('G-TEST');
});

it('exposes a verified purchase to the success page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->mock(SubscriptionCheckoutService::class, function ($mock): void {
        $mock->shouldReceive('receipt')->once()->andReturn([
            'transaction_id' => 'cs_test_123',
            'value' => 15.0,
            'currency' => 'EUR',
            'items' => [[
                'item_id' => '250gb',
                'item_name' => '250 GB',
                'item_variant' => 'monthly',
                'price' => 15.0,
                'quantity' => 1,
            ]],
        ]);
    });

    actingAs($user)
        ->get('/subscribe?checkout=success&session_id=cs_test_123')
        ->assertOk()
        ->assertSee('cs_test_123');
});

it('sends the purchase through the measurement protocol', function () {
    Http::fake();

    config([
        'analytics.enabled' => true,
        'analytics.ga4.measurement_id' => 'G-TEST',
        'analytics.ga4.api_secret' => 'secret',
    ]);

    (new SendGa4Purchase([
        'client_id' => '123456789.1699999999',
        'user_id' => '1',
        'transaction_id' => 'cs_test_123',
        'value' => 15.0,
        'currency' => 'EUR',
        'items' => [['item_id' => '250gb', 'item_name' => '250 GB', 'price' => 15.0, 'quantity' => 1]],
    ]))->handle(app(AnalyticsService::class));

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'measurement_id=G-TEST')
        && str_contains($request->url(), 'api_secret=secret')
        && $request['events'][0]['name'] === 'purchase'
        && $request['events'][0]['params']['transaction_id'] === 'cs_test_123');
});

it('does not send server-side purchases when analytics is disabled', function () {
    Http::fake();

    config([
        'analytics.enabled' => false,
        'analytics.ga4.measurement_id' => 'G-TEST',
        'analytics.ga4.api_secret' => 'secret',
    ]);

    (new SendGa4Purchase([
        'client_id' => '123456789.1699999999',
        'transaction_id' => 'cs_test_123',
        'value' => 15.0,
        'currency' => 'EUR',
    ]))->handle(app(AnalyticsService::class));

    Http::assertNothingSent();
});
