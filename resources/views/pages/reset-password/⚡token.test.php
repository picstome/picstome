<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('resetting the password logs out existing sessions', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $originalToken = $user->remember_token;

    $token = Password::createToken($user);

    DB::table('sessions')->insert([
        'id' => 'other-browser-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Firefox)',
        'payload' => base64_encode('session-payload'),
        'last_activity' => time(),
    ]);

    $response = Livewire::test('pages::reset-password.token', ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect($user->refresh()->remember_token)->not->toBe($originalToken);
});
