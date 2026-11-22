<?php

use App\Jobs\AddToAcumbamailList;
use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\LoginLink;
use App\Notifications\StorageAlmostFull;
use App\Services\MagicLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;

uses(RefreshDatabase::class);

function magicTokenFromUrl(string $url): string
{
    return basename((string) parse_url($url, PHP_URL_PATH));
}

it('emails a login link to existing users', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'magic@example.com']);

    Livewire::test('pages::login')
        ->submit([
            'form.email' => 'magic@example.com',
        ], 'sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('linkSent', true)
        ->assertSet('sentTo', 'magic@example.com')
        ->assertSee('magic@example.com')
        ->assertSee('spam');

    Notification::assertSentTo(
        $user,
        LoginLink::class,
        fn (LoginLink $notification): bool => str_contains($notification->url, '/auth/magic/'),
    );

    expect(MagicLoginToken::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not reveal whether an email belongs to an account', function () {
    Notification::fake();

    Livewire::test('pages::login')
        ->submit([
            'form.email' => 'missing@example.com',
        ], 'sendMagicLink')
        ->assertHasNoErrors()
        ->assertSet('linkSent', true)
        ->assertSet('sentTo', 'missing@example.com');

    Notification::assertNothingSent();
    expect(MagicLoginToken::query()->count())->toBe(0);
});

it('reveals the password form on request', function () {
    Livewire::test('pages::login')
        ->assertDontSee('Forgot your password?')
        ->set('showPassword', true)
        ->assertSee('Forgot your password?');
});

it('throttles login link requests per email', function () {
    Notification::fake();

    User::factory()->create(['email' => 'magic@example.com']);

    foreach (range(1, 3) as $attempt) {
        Livewire::test('pages::login')
            ->submit([
                'form.email' => 'magic@example.com',
            ], 'sendMagicLink')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::login')
        ->submit([
            'form.email' => 'magic@example.com',
        ], 'sendMagicLink')
        ->assertHasErrors('form.email');
});

it('logs in directly with a valid link, verifies the email and returns to the stored URL', function () {
    Queue::fake();

    $user = User::factory()->unverified()->create();

    $url = app(MagicLoginLink::class)->for(
        $user,
        '/subscribe?plan=pro&ps_from=bf2026',
    );

    $this->get($url)->assertRedirect('/subscribe?plan=pro&ps_from=bf2026');

    assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Queue::assertPushed(AddToAcumbamailList::class);
});

it('keeps the link usable until it expires', function () {
    $user = User::factory()->create();
    $token = magicTokenFromUrl(app(MagicLoginLink::class)->for($user));

    expect(app(MagicLoginLink::class)->consume($token))->not->toBeNull();
    expect(app(MagicLoginLink::class)->consume($token))->not->toBeNull();
    expect(MagicLoginToken::query()->first()?->used_at)->not->toBeNull();
});

it('rejects an expired link', function () {
    $user = User::factory()->create();

    $plain = bin2hex(random_bytes(32));

    MagicLoginToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'expires_at' => now()->subMinute(),
    ]);

    $this->get(route('login.magic', ['token' => $plain]))
        ->assertRedirect(route('login'));

    expect(app(MagicLoginLink::class)->find($plain))->toBeNull();
});

it('ignores an unsafe return URL', function () {
    $user = User::factory()->create();
    $url = app(MagicLoginLink::class)->for(
        $user,
        'https://evil.example.com/subscribe',
    );

    expect(MagicLoginToken::query()->where('user_id', $user->id)->first()?->redirect)->toBeNull();

    $this->get($url)->assertRedirect('/');
});

it('switches to the account the link belongs to', function () {
    $current = User::factory()->create();
    $target = User::factory()->create();

    actingAs($current);

    $this->get(app(MagicLoginLink::class)->for($target));

    assertAuthenticatedAs($target->fresh());
});

it('keeps the intended URL and its query string in the login link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->get('/galleries?foo=bar&utm_source=newsletter')
        ->assertRedirect('/login');

    Livewire::test('pages::login')
        ->submit([
            'form.email' => $user->email,
        ], 'sendMagicLink');

    $token = MagicLoginToken::query()
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();

    expect($token?->redirect)->toBe('/galleries?foo=bar&utm_source=newsletter');
});

it('adds a magic login link to user-facing emails', function () {
    $user = User::factory()->create();

    $mail = (new StorageAlmostFull(100, 200, '/subscribe'))->toMail($user);

    expect(implode(' ', [...$mail->introLines, ...$mail->outroLines]))->toContain('/auth/magic/');
});

it('prunes expired tokens', function () {
    $user = User::factory()->create();

    MagicLoginToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'expired'),
        'expires_at' => now()->subDay(),
    ]);

    MagicLoginToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'valid'),
        'expires_at' => now()->addMinute(),
    ]);

    expect(app(MagicLoginLink::class)->prune())->toBe(1);
    expect(MagicLoginToken::query()->count())->toBe(1);
});
