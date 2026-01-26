<?php

use App\Models\Contract;
use App\Models\Photoshoot;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ContractExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
    Storage::fake('s3');
    Notification::fake();

    $contract = Contract::factory()->for($this->team)->create(['ulid' => '1234ABC', 'title' => 'The Contract']);
    $signatureA = Signature::factory()->signed()->make(['email' => 'john@example.com']);
    $signatureB = Signature::factory()->signed()->make(['email' => 'jane@example.com']);
    $contract->signatures()->saveMany([$signatureA, $signatureB]);

    $component = Livewire::actingAs($this->user)->test('pages::contracts.show', ['contract' => $contract])->call('execute');

    tap($contract->fresh(), function (Contract $contract) {
        expect($contract->executed_at)->not->toBeNull();
        expect($contract->pdf_file_path)->not->toBeNull();
        expect($contract->pdf_file_path)->toContain('teams/1/contracts/1234ABC');
        Storage::disk('s3')->assertExists($contract->pdf_file_path);
        Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'john@example.com';
        });
        Notification::assertSentOnDemand(ContractExecuted::class, function (ContractExecuted $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'jane@example.com';
        });
    });
});

test('contract cannot be executed if there are remaining signatures to sign', function () {
    $contract = Contract::factory()->for($this->team)->create(['title' => 'The Contract']);
    $signatureA = Signature::factory()->signed()->make(['email' => 'john@example.com']);
    $signatureB = Signature::factory()->unsigned()->make();
    $contract->signatures()->saveMany([$signatureA, $signatureB]);

    $component = Livewire::actingAs($this->user)->test('pages::contracts.show', ['contract' => $contract])->call('execute');

    $component->assertStatus(401);
    expect($contract->executed_at)->toBeNull();
});

test('can download an executed contract', function () {
    Storage::fake('s3');
    $contract = Contract::factory()->for($this->team)->executed()->create([
        'title' => 'Contract',
        'pdf_file_path' => UploadedFile::fake()->create('contract.pdf')
            ->storeAs('contracts/1/contract.pdf', 'contract.pdf', 's3'),
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::contracts.show', ['contract' => $contract])
        ->call('download');

    $component->assertFileDownloaded('contract.pdf');
});

test('user can assign a contract to a photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for(
        $this->team
    )->create();
    $contract = Contract::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->actingAs($this->team->owner)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->set('photoshoot_id', $photoshoot->id)
        ->call('assignToPhotoshoot');

    expect($contract->fresh()->photoshoot->is($photoshoot))->toBeTrue();
});

test('user cannot assign a contract to a photoshoot from another team', function () {
    $otherTeam = Team::factory()->create();
    $photoshoot = Photoshoot::factory()->for($otherTeam)->create();
    $contract = Contract::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->team->owner)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->set('photoshoot_id', $photoshoot->id)
        ->call('assignToPhotoshoot')
        ->assertHasErrors(['photoshoot_id' => 'exists']);

    expect($contract->fresh()->photoshoot_id)->toBeNull();
});

test('user can re-assign a contract to a different photoshoot', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for($this->team)->create();
    $contract = Contract::factory()->for($this->team)->create(['photoshoot_id' => $photoshootA->id]);

    $component = Livewire::actingAs($this->team->owner)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->set('photoshoot_id', $photoshootB->id)
        ->call('assignToPhotoshoot');

    expect($contract->fresh()->photoshoot->is($photoshootB))->toBeTrue();
});

test('user can re-assign a contract null photoshoot', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for($this->team)->create();
    $contract = Contract::factory()->for($this->team)->create(['photoshoot_id' => $photoshootA->id]);

    $component = Livewire::actingAs($this->team->owner)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->set('photoshoot_id', null)
        ->call('assignToPhotoshoot');

    expect($contract->fresh()->photoshoot_id)->toBeNull();
});

test('can delete team contract', function () {
    Storage::fake('s3');
    $contract = Contract::factory()->for($this->team)->has(
        Signature::factory()->count(2)->sequence(
            ['signature_image_path' => UploadedFile::fake()
                ->image('signature.png')
                ->storeAs('signatures', 'signatureA.png', 's3'),
            ],
            ['signature_image_path' => UploadedFile::fake()
                ->image('signature.png')
                ->storeAs('signatures', 'signatureB.png', 's3'),
            ],
        ))->create([
            'pdf_file_path' => UploadedFile::fake()
                ->create('contract.pdf')
                ->storeAs('contracts', 'contract.pdf', 's3'),
        ]);
    expect(Signature::count())->toBe(2);
    Storage::disk('s3')->assertExists('contracts/contract.pdf');
    Storage::disk('s3')->assertExists('signatures/signatureA.png');
    Storage::disk('s3')->assertExists('signatures/signatureA.png');

    $component = Livewire::actingAs($this->user)->test('pages::contracts.show', ['contract' => $contract])
        ->call('delete');

    $component->assertRedirect('/contracts');
    expect(Contract::count())->toBe(0);
    expect(Signature::count())->toBe(0);
    Storage::disk('s3')->assertMissing('contracts/contract.pdf');
    Storage::disk('s3')->assertMissing('signatures/signatureA.png');
    Storage::disk('s3')->assertMissing('signatures/signatureA.png');
});
