<?php

use App\Models\Contract;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ContractExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\LaravelPdf\Facades\Pdf;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team contract', function () {
    $contract = Contract::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/contracts/1');

    $response->assertStatus(200);
    $response->assertViewHas('contract');
    expect($response['contract']->is($contract))->toBeTrue();
});

test('guests cannot view any contracts', function () {
    Contract::factory()->create();

    $response = get('/contracts/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team contracts of others', function () {
    $contract = Contract::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/contracts/1');

    $response->assertStatus(403);
});

test('contract can be executed once all parties have signed it', function () {
    Pdf::fake();
    Notification::fake();

    $contract = Contract::factory()->for($this->team)->create(['ulid' => '1234ABC', 'title' => 'The Contract']);
    $signatureA = Signature::factory()->signed()->make(['email' => 'john@example.com']);
    $signatureB = Signature::factory()->signed()->make(['email' => 'jane@example.com']);
    $contract->signatures()->saveMany([$signatureA, $signatureB]);

    $component = Volt::test('pages.contracts.show', ['contract' => $contract])->call('execute');

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

test('contract cannot be executed if there are remaining signatures to sign', function () {
    $contract = Contract::factory()->create(['title' => 'The Contract']);
    $signatureA = Signature::factory()->signed()->make(['email' => 'john@example.com']);
    $signatureB = Signature::factory()->unsigned()->make();
    $contract->signatures()->saveMany([$signatureA, $signatureB]);

    $component = Volt::test('pages.contracts.show', ['contract' => $contract])->call('execute');

    $component->assertStatus(401);
    expect($contract->executed_at)->toBeNull();
});

test('can download an executed contract', function () {
    Storage::fake('public');
    $contract = Contract::factory()->executed()->create([
        'title' => 'Contract',
        'pdf_file_path' => UploadedFile::fake()->create('contract.pdf')
            ->storeAs('contracts/1/contract.pdf', 'contract.pdf', 'public'),
    ]);

    $component = Volt::test('pages.contracts.show', ['contract' => $contract])
        ->call('download');

    $component->assertFileDownloaded('contract.pdf');
});

test('can delete team contract', function () {
    Storage::fake('public');
    $contract = Contract::factory()->has(
        Signature::factory()->count(2)->sequence(
            ['signature_image_path' => UploadedFile::fake()
                ->image('signature.png')
                ->storeAs('signatures', 'signatureA.png', 'public'),
            ],
            ['signature_image_path' => UploadedFile::fake()
                ->image('signature.png')
                ->storeAs('signatures', 'signatureB.png', 'public'),
            ],
        ))->create([
            'pdf_file_path' => UploadedFile::fake()
                ->create('contract.pdf')
                ->storeAs('contracts', 'contract.pdf', 'public'),
        ]);
    expect(Signature::count())->toBe(2);
    Storage::disk('public')->assertExists('contracts/contract.pdf');
    Storage::disk('public')->assertExists('signatures/signatureA.png');
    Storage::disk('public')->assertExists('signatures/signatureA.png');

    $component = Volt::test('pages.contracts.show', ['contract' => $contract])
        ->call('delete');

    $component->assertRedirect('/contracts');
    expect(Contract::count())->toBe(0);
    expect(Signature::count())->toBe(0);
    Storage::disk('public')->assertMissing('contracts/contract.pdf');
    Storage::disk('public')->assertMissing('signatures/signatureA.png');
    Storage::disk('public')->assertMissing('signatures/signatureA.png');
});
