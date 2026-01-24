<?php

use App\Jobs\ProcessPdfContract;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ContractExecuted;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests can sign a contract', function () {
    Storage::fake('s3');
    Queue::fake();

    $signature = Signature::factory()->unsigned()->create(['ulid' => '0123ABC']);

    $response = get('/signatures/0123ABC');
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
        Storage::disk('s3')->assertExists($signature->signature_image_path);
        Queue::assertPushed(ProcessPdfContract::class);
    });
});

test('contract is executed once the final signature is submitted', function () {
    Storage::fake('s3');
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
        Storage::disk('s3')->assertExists($contract->pdf_file_path);
    });

    Notification::assertCount(2);
    Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'john@example.com';
    });
    Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'jane@example.com';
    });
});

test('contract is not executed if there are remaining signatures to sign', function () {
    Storage::fake('s3');
    $contract = Contract::factory()->create(['ulid' => '1234ABC', 'title' => 'The Contract']);
    $contract->addSignatures(2);
    expect($contract->signaturesRemaining())->toBe(2);
    $signature = $contract->signatures()->unsigned()->first();

    $component = Volt::actingAs($contract->team->owner)->test('pages.contracts.show', ['contract' => $contract])->call('execute');

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

test('signing a contract updates the customer birthdate if customer exists for team and email', function () {
    Storage::fake('s3');
    Queue::fake();

    $team = Team::factory()->create();
    $customer = Customer::factory()->for($team)->create([
        'email' => 'john@example.com',
        'birthdate' => null,
    ]);
    $contract = Contract::factory()->for($team)->create();
    $signature = Signature::factory()->for($contract)->unsigned()->create();

    $newBirthdate = '2000-12-12';

    $component = Volt::test('pages.signatures.sign', ['signature' => $signature])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', $newBirthdate)
        ->set('email', 'john@example.com')
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    expect($customer->fresh()->birthdate->toDateString())->toBe($newBirthdate);
});

test('signing updates photoshoot customer email and birthdate when not team owner and contract has two signatures', function () {
    Storage::fake('s3');
    Queue::fake();

    $team = Team::factory()->create();
    $photoshootCustomer = Customer::factory()->for($team)->create([
        'email' => 'old-email@example.com',
        'birthdate' => '1990-01-01',
    ]);
    $photoshoot = \App\Models\Photoshoot::factory()->for($team)->create([
        'customer_id' => $photoshootCustomer->id,
    ]);
    $contract = Contract::factory()->for($team)->for($photoshoot)->create();
    $contract->addSignatures(2);

    $newEmail = 'new-email@example.com';
    $newBirthdate = '2000-12-12';

    Volt::test('pages.signatures.sign', ['signature' => $contract->signatures()->unsigned()->first()])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', $newBirthdate)
        ->set('email', $newEmail)
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    expect($photoshootCustomer->fresh()->email)->toBe($newEmail);
    expect($photoshootCustomer->fresh()->birthdate->toDateString())->toBe($newBirthdate);
});

test('signing does not update photoshoot customer when signer is team owner', function () {
    Storage::fake('s3');
    Queue::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create(['user_id' => $owner->id]);
    $photoshootCustomer = Customer::factory()->for($team)->create([
        'email' => 'old-email@example.com',
        'birthdate' => '1990-01-01',
    ]);
    $photoshoot = \App\Models\Photoshoot::factory()->for($team)->create([
        'customer_id' => $photoshootCustomer->id,
    ]);
    $contract = Contract::factory()->for($team)->for($photoshoot)->create();
    $contract->addSignatures(2);

    $newBirthdate = '2000-12-12';

    Volt::test('pages.signatures.sign', ['signature' => $contract->signatures()->unsigned()->first()])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', $newBirthdate)
        ->set('email', 'owner@example.com')
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    expect($photoshootCustomer->fresh()->email)->toBe('old-email@example.com');
    expect($photoshootCustomer->fresh()->birthdate->toDateString())->toBe('1990-01-01');
});

test('signing does not update photoshoot customer when contract has only one signature', function () {
    Storage::fake('s3');
    Queue::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create(['user_id' => $owner->id]);
    $photoshootCustomer = Customer::factory()->for($team)->create([
        'email' => 'old-email@example.com',
        'birthdate' => '1990-01-01',
    ]);
    $photoshoot = \App\Models\Photoshoot::factory()->for($team)->create([
        'customer_id' => $photoshootCustomer->id,
    ]);
    $contract = Contract::factory()->for($team)->for($photoshoot)->create();
    $contract->addSignatures(1);

    $newEmail = 'new-email@example.com';
    $newBirthdate = '2000-12-12';

    Volt::test('pages.signatures.sign', ['signature' => $contract->signatures()->unsigned()->first()])
        ->set('role', 'Model')
        ->set('legalName', 'John Doe')
        ->set('documentNumber', 'ABC1234')
        ->set('nationality', '::nationality::')
        ->set('birthday', $newBirthdate)
        ->set('email', $newEmail)
        ->set('signature_image', UploadedFile::fake()->image('signature.png'))
        ->call('sign');

    expect($photoshootCustomer->fresh()->email)->toBe('old-email@example.com');
    expect($photoshootCustomer->fresh()->birthdate->toDateString())->toBe('1990-01-01');
});
