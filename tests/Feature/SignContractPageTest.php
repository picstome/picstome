<?php

use App\Models\Contract;
use App\Models\Signature;
use App\Models\User;
use App\Notifications\ContractExecuted;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\LaravelPdf\Facades\Pdf;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests can sign a contract', function () {
    Storage::fake('public');

    $signature = Signature::factory()->unsigned()->create(['ulid' => '0123ABC']);

    $response = get('/signatures/0123ABC/sign');
    $component = Volt::test('pages.signatures.sign', ['signature' => $signature])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', Carbon::parse('2000-12-12'))
        ->set('email', 'john@example.com')
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    tap($signature->fresh(), function (Signature $signature) {
        expect($signature->isSigned())->toBeTrue();
        expect($signature->role)->toBe('Model');
        expect($signature->legal_name)->toBe('John Doe');
        expect($signature->document_number)->toBe('ABC1234');
        expect((string) $signature->birthday)->toBe('2000-12-12 00:00:00');
        expect($signature->email)->toBe('john@example.com');
        expect($signature->signature_image_path)->not->toBeNull();
        expect($signature->signature_image_url)->not->toBeNull();
        Storage::disk('public')->assertExists($signature->signature_image_path);
    });
});

test('contract is executed once the final signature is submitted', function () {
    Storage::fake('public');
    Pdf::fake();
    Notification::fake();

    $contract = Contract::factory()->create(['ulid' => '1234ABC', 'title' => 'The Contract']);
    $contract->addSignatures(2);
    tap($contract->signatures()->first(), function (Signature $signature) {
        $signature->update(['email' => 'john@example.com']);
    })->markAsSigned();
    expect($contract->signaturesRemaining())->toBe(1);
    $lastUnsingedSignature = $contract->signatures()->unsigned()->first();

    $component = Volt::test('pages.signatures.sign', ['signature' => $lastUnsingedSignature])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', Carbon::parse('2000-12-12'))
        ->set('email', 'jane@example.com')
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    tap($contract->fresh(), function (Contract $contract) {
        expect($contract->executed_at)->not->toBeNull();
        expect($contract->pdf_file_path)->not->toBeNull();
        expect($contract->pdf_file_path)->toContain('teams/1/contracts/1234ABC');
        Pdf::assertSaved(function ($pdf, $path) use ($contract) {
            return $path === $contract->pdf_file_path;
        });
        Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'john@example.com';
        });
        Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'jane@example.com';
        });
    });
});

test('contract is not executed if there are remaining signatures to sign', function () {
    Storage::fake('public');
    $contract = Contract::factory()->create(['ulid' => '1234ABC', 'title' => 'The Contract']);
    $contract->addSignatures(2);
    expect($contract->signaturesRemaining())->toBe(2);
    $signature = $contract->signatures()->unsigned()->first();

    $component = Volt::test('pages.contracts.show', ['contract' => $contract])->call('execute');

    $component = Volt::test('pages.signatures.sign', ['signature' => $signature])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', Carbon::parse('2000-12-12'))
        ->set('email', 'jane@example.com')
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    expect($signature->fresh()->isSigned())->toBeTrue();
    expect($contract->executed_at)->toBeNull();
});

test('sign form can be pre-filled when the user is logged in', function () {
    $user = User::factory()->create(['email' => 'test-user@example.com']);
    $signature = Signature::factory()->signed()->create([
        'role' => '::role::',
        'legal_name' => '::legal-name::',
        'nationality' => '::nationality::',
        'document_number' => '::document-number::',
        'email' => 'test-user@example.com',
        'birthday' => Carbon::parse('2000-12-12'),
    ]);

    $signature = Signature::factory()->unsigned()->create(['ulid' => '0123ABC']);

    $component = Volt::actingAs($user)->test('pages.signatures.sign', ['signature' => $signature]);

    expect($component->email)->toBe('test-user@example.com');
    expect($component->legalName)->toBe('::legal-name::');
    expect($component->nationality)->toBe('::nationality::');
    expect($component->birthday)->toBe('2000-12-12');
    expect($component->role)->toBe('::role::');
    expect($component->documentNumber)->toBe('::document-number::');
});

test('sign form is not pre-filled when the user does not have a signed signature', function () {
    $user = User::factory()->create(['email' => 'test-user@example.com']);

    $signature = Signature::factory()->unsigned()->create(['ulid' => '0123ABC']);

    $component = Volt::actingAs($user)->test('pages.signatures.sign', ['signature' => $signature]);

    expect($component->email)->toBeNull();
    expect($component->legalName)->toBeNull();
    expect($component->nationality)->toBeNull();
    expect($component->birthday)->toBeNull();
    expect($component->role)->toBeNull();
    expect($component->documentNumber)->toBeNull();
});
