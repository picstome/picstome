<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $this->admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
});

it('allows an admin to view the email catalog', function () {
    actingAs($this->admin)
        ->get('/admin/emails')
        ->assertOk()
        ->assertSee('Emails')
        ->assertSee('Email verification')
        ->assertSee('Storage almost full')
        ->assertSee('In-app banner')
        ->assertSee('Edit texts');
});

it('forbids access to the email catalog for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/emails')->assertForbidden();
});

it('lists every catalogued email', function () {
    $response = actingAs($this->admin)->get('/admin/emails')->assertOk();

    foreach (config('emails') as $email) {
        $response->assertSee($email['label']['en']);
    }
});

it('opens the filtered translations for an email', function () {
    $this->actingAs($this->admin);

    visit('/admin/emails')
        ->assertSee('Storage almost full')
        ->click('[data-test="edit-email-storage_almost_full"]')
        ->assertSee('Filtered by email')
        ->assertSee('Your storage is almost full')
        ->assertNoJavascriptErrors();
});

it('does not scroll horizontally', function (int $width) {
    $this->actingAs($this->admin);

    visit('/admin/emails')
        ->resize($width, 900)
        ->assertSee('Emails')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth + 1', true)
        ->assertNoJavascriptErrors();
})->with([1024, 1280, 1440]);
