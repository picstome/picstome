<?php

use App\Jobs\ProcessPdfContract;
use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

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
