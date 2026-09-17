<?php

use App\Jobs\NotifyContractExecuted;
use App\Models\Contract;
use App\Models\Signature;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ContractExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies each signer in the contract owner language', function () {
    Notification::fake();

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();
    Signature::factory()->signed()->count(2)->sequence(
        ['email' => 'one@example.com'],
        ['email' => 'two@example.com'],
    )->for($contract)->create();

    (new NotifyContractExecuted($contract))->handle();

    Notification::assertSentOnDemandTimes(ContractExecuted::class, 2);
    Notification::assertSentOnDemand(
        ContractExecuted::class,
        fn (ContractExecuted $notification) => $notification->locale === 'es'
    );
});

it('falls back to the default locale when the owner has none', function () {
    Notification::fake();

    $owner = User::factory()->create(['language' => null]);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create();
    Signature::factory()->signed()->for($contract)->create();

    (new NotifyContractExecuted($contract))->handle();

    Notification::assertSentOnDemand(
        ContractExecuted::class,
        fn (ContractExecuted $notification) => $notification->locale === config('app.locale')
    );
});

it('renders the mail with translated strings', function () {
    app()->setLocale('es');

    $owner = User::factory()->create(['language' => 'es']);
    $team = Team::factory()->for($owner, 'owner')->create();
    $contract = Contract::factory()->for($team)->create(['pdf_file_path' => 'contracts/example.pdf']);

    $mail = (new ContractExecuted($contract))->toMail($owner);

    expect($mail->subject)->toBe('Firmado: The Contract');
    expect((string) $mail->render())->toContain('Contrato firmado', 'Descargar contrato');
});
