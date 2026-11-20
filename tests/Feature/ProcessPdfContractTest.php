<?php

use App\Jobs\ProcessPdfContract;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractPdfFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('notifies the owner when pdf generation fails after all retries', function () {
    Notification::fake();

    $contract = Contract::factory()->for($this->team)->create();

    (new ProcessPdfContract($contract))->failed(new RuntimeException('renderer exploded'));

    Notification::assertSentTo($contract->team->owner, ContractPdfFailed::class);
});

it('stays silent when the contract disappeared before the failure arrived', function () {
    Notification::fake();

    $contract = Contract::factory()->for($this->team)->create();
    $job = new ProcessPdfContract($contract);

    Contract::query()->whereKey($contract->getKey())->delete();

    $job->failed(new RuntimeException('renderer exploded'));

    Notification::assertNothingSent();
});

it('queues a new pdf generation for an executed contract without a pdf', function () {
    Queue::fake();

    $contract = Contract::factory()->for($this->team)->executed()->create();

    Livewire::actingAs($this->user)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->call('retryPdf');

    Queue::assertPushed(ProcessPdfContract::class);
});

it('refuses to generate a pdf for a contract that was not executed', function () {
    Queue::fake();

    $contract = Contract::factory()->for($this->team)->create();

    Livewire::actingAs($this->user)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->call('retryPdf')
        ->assertStatus(403);

    Queue::assertNothingPushed();
});

it('refuses to generate a duplicate pdf when one already exists', function () {
    Queue::fake();

    $contract = Contract::factory()->for($this->team)->executed()->create([
        'pdf_file_path' => 'contracts/existing.pdf',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::contracts.show', ['contract' => $contract])
        ->call('retryPdf')
        ->assertStatus(409);

    Queue::assertNothingPushed();
});
