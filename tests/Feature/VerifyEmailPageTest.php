<?php

use App\Jobs\AddToAcumbamailList;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

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
