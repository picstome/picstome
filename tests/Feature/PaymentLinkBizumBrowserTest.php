<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('opens the Bizum form from the payment link page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
    ]);

    $page = visit('/@'.$team->handle.'/pay/120/Session');

    $page->assertSee('Pay with Bizum')
        ->click('Pay with Bizum')
        ->wait(0.5)
        ->assertSee('Continue with Bizum')
        ->assertNoJavaScriptErrors();
})->group('browser');

it('shows Bizum first and card next to it when both are available', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->markOnboarded();
    $team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
    ]);

    $page = visit('/@'.$team->handle.'/pay/120/Session');

    $page->assertSee('Pay with Bizum')
        ->assertSee('Pay by card');

    $boxes = json_decode($page->script(<<<'JS'
(() => {
    const buttons = Array.from(document.querySelectorAll("button"));
    const bizum = buttons.find(b => b.textContent.includes("Pay with Bizum")).getBoundingClientRect();
    const card = buttons.find(b => b.textContent.includes("Pay by card")).getBoundingClientRect();

    return JSON.stringify({ bizum, card });
})()
JS), true);

    expect(abs($boxes['bizum']['top'] - $boxes['card']['top']))->toBeLessThan(2)
        ->and($boxes['bizum']['left'])->toBeLessThan($boxes['card']['left']);
})->group('browser');
