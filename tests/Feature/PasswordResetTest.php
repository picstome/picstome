<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders the reset password link screen', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

it('sends a reset password link when requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('pages.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset password screen with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('pages.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

it('resets the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('pages.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = Volt::test('pages.reset-password.token', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('resetPassword');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
