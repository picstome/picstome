<?php

use App\Jobs\AddToAcumbamailList;
use App\Models\User;
use App\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('Email Verification', function () {
    it('shows the verification screen to unverified users', function () {
        $user = User::factory()->unverified()->create();

        actingAs($user)
            ->get('/verify-email')
            ->assertStatus(200);
    });

    it('verifies the email when visiting a valid signed URL', function () {
        $user = User::factory()->unverified()->create();
        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
        $response->assertRedirect(route('galleries', absolute: false).'?verified=1');
    });

    it('does not verify the email with an invalid hash', function () {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        actingAs($user)->get($verificationUrl);

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('redirects to the return URL after verifying the email', function () {
        $user = User::factory()->unverified()->create();
        $redirect = '/subscribe?plan=pro&storage=250gb&billing=monthly&ps_aid=eyJzIjoidGVzdCJ9%3D%3D&ps_from=bf2026';

        $verificationUrl = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'redirect' => $redirect,
        ]);

        actingAs($user)->get($verificationUrl)->assertRedirect($redirect);

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('ignores an external return URL when verifying the email', function () {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'redirect' => 'https://evil.example.com/subscribe',
        ]);

        actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('galleries', absolute: false).'?verified=1');
    });

    it('sends guests who open the verification email to login with the return URL', function () {
        $user = User::factory()->unverified()->create();
        $redirect = '/subscribe?plan=pro&storage=250gb&ps_from=bf2026';

        $verificationUrl = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'redirect' => $redirect,
        ]);

        $this->get($verificationUrl)
            ->assertRedirect(route('login', ['redirect' => $redirect], absolute: false));

        expect(session('verification.url'))->toContain('/verify-email/'.$user->id);
    });

    it('completes the verification and returns to the subscribe URL after logging in', function () {
        $user = User::factory()->unverified()->create([
            'email' => 'verify-after-login@example.com',
            'password' => Hash::make('password'),
        ]);
        $redirect = '/subscribe?plan=pro&storage=250gb&billing=monthly&ps_from=bf2026';

        $verificationUrl = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'redirect' => $redirect,
        ]);

        $this->get($verificationUrl)
            ->assertRedirect(route('login', ['redirect' => $redirect], absolute: false));

        Livewire::test('pages::login')
            ->set('form.email', 'verify-after-login@example.com')
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect($verificationUrl);

        $this->get($verificationUrl)->assertRedirect($redirect);

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    });
});

it('includes the return URL in the verification email', function () {
    Notification::fake();

    Livewire::withQueryParams(['redirect' => '/subscribe?plan=pro&ps_from=bf2026'])
        ->test('pages::register')
        ->set('name', 'Return URL')
        ->set('email', 'return-url@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'return-url@example.com')->firstOrFail();

    Notification::assertSentTo(
        $user,
        VerifyEmailNotification::class,
        function (VerifyEmailNotification $notification) use ($user): bool {
            $actionUrl = $notification->toMail($user)->actionUrl;

            return is_string($actionUrl) && str_contains($actionUrl, http_build_query(
                ['redirect' => '/subscribe?plan=pro&ps_from=bf2026'],
                '',
                '&',
                PHP_QUERY_RFC3986,
            ));
        },
    );
});

it('adds new user to Acumbamail mailing list upon registration', function () {
    Queue::fake();
    Event::fake();
    $user = User::factory()->unverified()->create();

    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => '123']);
    config(['services.acumbamail.list_id_es' => '456']);

    app()->setLocale('en');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = actingAs($user)->get($verificationUrl);

    Queue::assertPushed(AddToAcumbamailList::class, function ($job) use ($user) {
        return $job->email === $user->email &&
               $job->name === $user->name &&
               $job->listId === '123';
    });
});

it('adds spanish users to spanish Acumbamail mailing list upon registration', function () {
    Queue::fake();
    Event::fake();
    $user = User::factory()->unverified()->create(['language' => 'es']);
    $user->save();

    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => '123']);
    config(['services.acumbamail.list_id_es' => '456']);

    app()->setLocale('es');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = actingAs($user)->get($verificationUrl);

    Queue::assertPushed(AddToAcumbamailList::class, function ($job) use ($user) {
        return $job->email === $user->email &&
               $job->name === $user->name &&
               $job->listId === '456';
    });
});
