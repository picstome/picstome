<?php

use App\Jobs\NotifyContractExecuted;
use App\Jobs\ProcessPdfContract;
use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ContractPdfFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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

it('renders the pdf in the contract owner language', function () {
    Storage::fake('s3');

    $renderedLocales = [];
    View::composer('pdf.contract', function () use (&$renderedLocales) {
        $renderedLocales[] = app()->getLocale();
    });

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();
    $contract->addSignatures(1);

    (new ProcessPdfContract($contract))->handle();

    expect($renderedLocales)->toBe(['es']);
    expect($contract->fresh()->pdf_file_path)->not->toBeNull();
});

it('keeps generated contract pdfs small by subsetting embedded fonts', function () {
    Storage::fake('s3');

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();
    $contract->addSignatures(1);

    (new ProcessPdfContract($contract))->handle();

    $contract->refresh();

    expect($contract->pdf_file_path)->not->toBeNull()
        ->and(Storage::disk('s3')->size($contract->pdf_file_path))->toBeLessThan(200_000);
});

it('generates the pdf in the owner language when a contract is executed', function () {
    Storage::fake('s3');

    $renderedLocales = [];
    View::composer('pdf.contract', function () use (&$renderedLocales) {
        $renderedLocales[] = app()->getLocale();
    });

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();

    $contract->execute();

    $contract = $contract->fresh();

    expect($contract->executed_at)->not->toBeNull();
    expect($renderedLocales)->toBe(['es']);
    expect($contract->pdf_file_path)->not->toBeNull();
});

it('renders the pdf in the default language when the owner has none', function () {
    Storage::fake('s3');

    $renderedLocales = [];
    View::composer('pdf.contract', function () use (&$renderedLocales) {
        $renderedLocales[] = app()->getLocale();
    });

    $owner = User::factory()->create(['language' => null]);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();

    (new ProcessPdfContract($contract))->handle();

    expect($renderedLocales)->toBe([config('app.locale')]);
});

it('restores the previous locale after rendering', function () {
    Storage::fake('s3');

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();

    (new ProcessPdfContract($contract))->handle();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('shows translated labels in the contract pdf view', function () {
    app()->setLocale('es');

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();
    $contract->addSignatures(1);

    $html = view('pdf.contract', ['contract' => $contract])->render();

    expect($html)->toContain(
        'Ubicación',
        'Fecha de la sesión',
        'Términos del contrato',
        'Nombre legal',
        'Rol',
        'Cumpleaños',
        'Nacionalidad',
        'Número de documento',
        'Correo electrónico',
    );
});

it('does not regenerate the pdf or notify again when one already exists', function () {
    Storage::fake('s3');
    Queue::fake();

    $contract = Contract::factory()->for($this->team)->executed()->create([
        'pdf_file_path' => 'contracts/existing.pdf',
    ]);

    (new ProcessPdfContract($contract))->handle();

    expect($contract->fresh()->pdf_file_path)->toBe('contracts/existing.pdf');
    Queue::assertNotPushed(NotifyContractExecuted::class);
});

it('queues a single pdf generation per contract while one is pending', function () {
    Queue::fake();

    $contract = Contract::factory()->for($this->team)->executed()->create();

    ProcessPdfContract::dispatch($contract);
    ProcessPdfContract::dispatch($contract);

    Queue::assertPushed(ProcessPdfContract::class, 1);
});

it('restores the previous locale when rendering fails', function () {
    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();

    View::composer('pdf.contract', fn () => throw new RuntimeException('renderer exploded'));

    expect(fn () => (new ProcessPdfContract($contract))->handle())->toThrow(RuntimeException::class);
    expect(app()->getLocale())->toBe(config('app.locale'));
});
