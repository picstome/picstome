<?php

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BookingCreated;
use App\Notifications\ContractExecuted;
use App\Notifications\StorageAlmostFull;
use App\Support\MailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    $this->owner = User::factory()->create();
    $this->team = Team::factory()->for($this->owner, 'owner')->create([
        'name' => 'Monkeyshot',
        'brand_color' => 'rose',
        'brand_font' => 'Playfair Display',
    ]);
});

function bookingCreated(Team $team): BookingCreated
{
    return new BookingCreated(
        Photoshoot::factory()->for($team)->create(['name' => 'Family session']),
        Carbon::parse('2026-12-20'),
        Carbon::parse('10:00'),
        Carbon::parse('11:00'),
        Payment::factory()->for($team)->create(),
        'Europe/Madrid',
    );
}

test('emails to photographers carry the Picstome brand', function () {
    $html = (string) (new StorageAlmostFull(9, 10, 'https://picstome.com/subscribe'))
        ->toMail($this->owner)
        ->render();

    expect($html)
        ->toContain(asset('app-logo.png'))
        ->toContain('alt="Picstome"')
        ->toContain('#316d61')
        ->toContain("'Inter'")
        ->toContain('family=Inter')
        ->toContain('© '.date('Y').' Picstome')
        ->not->toContain('#18181b;"'); // Laravel's default button colour.
});

test('the signed contract email carries the photographer brand', function () {
    $contract = Contract::factory()->for($this->team)->create(['pdf_file_path' => 'contracts/signed.pdf']);

    $html = (string) (new ContractExecuted($contract))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect($html)
        ->toContain('Monkeyshot')
        ->toContain('#e11d48')
        ->toContain("'Playfair Display'")
        ->toContain('family=Playfair+Display')
        ->toContain('© '.date('Y').' Monkeyshot')
        ->not->toContain(asset('app-logo.png'))
        ->not->toContain('#316d61');
});

test('the photographer logo replaces the team name in client emails', function () {
    $this->team->update(['brand_logo_path' => 'logos/monkeyshot.png']);
    $contract = Contract::factory()->for($this->team)->create(['pdf_file_path' => 'contracts/signed.pdf']);

    $html = (string) (new ContractExecuted($contract))
        ->toMail(new AnonymousNotifiable)
        ->render();

    // The plain stored image, never the WebP CDN variant Outlook cannot show.
    expect($html)
        ->toContain(Storage::disk(config('picstome.disk'))->url('logos/monkeyshot.png').'"')
        ->toContain('alt="Monkeyshot"')
        ->not->toContain('brand-name');
});

test('client emails of teams without a subscription say they are powered by Picstome', function () {
    $contract = Contract::factory()->for($this->team)->create(['pdf_file_path' => 'contracts/signed.pdf']);

    $html = (string) (new ContractExecuted($contract))->toMail(new AnonymousNotifiable)->render();
    expect($html)->toContain('Powered by')->toContain('https://picstome.com');

    $this->team->update(['lifetime_at' => now()]);

    $html = (string) (new ContractExecuted($contract->fresh()))->toMail(new AnonymousNotifiable)->render();
    expect($html)->not->toContain('Powered by');
});

test('the booking email is branded for the payer and stays Picstome for the photographer', function () {
    $notification = bookingCreated($this->team);

    $payerCopy = (string) $notification->toMail(new AnonymousNotifiable)->render();
    $ownerCopy = (string) $notification->toMail($this->owner)->render();

    expect($payerCopy)->toContain('#e11d48')->not->toContain(asset('app-logo.png'));
    expect($ownerCopy)->toContain('#316d61')->toContain(asset('app-logo.png'));
});

test('teams without a colour or font fall back to the Picstome ones', function () {
    $this->team->update(['brand_color' => null, 'brand_font' => null]);

    $brand = MailBranding::forTeam($this->team->fresh());

    expect($brand->accent)->toBe('#316d61')
        ->and($brand->font)->toBe('Inter')
        ->and($brand->logoUrl)->toBeNull()
        ->and($brand->name)->toBe('Monkeyshot');
});

test('the Picstome email logo can point at a public copy', function () {
    config(['picstome.mail_logo_url' => 'https://app.picstome.com/app-logo.png']);

    expect(MailBranding::picstome()->logoUrl)->toBe('https://app.picstome.com/app-logo.png');
});
