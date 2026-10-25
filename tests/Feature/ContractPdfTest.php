<?php

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function executedContractWithPdf(Team $team): Contract
{
    Storage::fake(config('picstome.disk'));

    $contract = Contract::factory()->for($team)->executed()->create([
        'title' => 'Contract',
    ]);

    $contract->update([
        'pdf_file_path' => UploadedFile::fake()
            ->create('contract.pdf')
            ->storeAs("contracts/{$contract->id}/contract.pdf", 'contract.pdf', config('picstome.disk')),
    ]);

    return $contract;
}

it('downloads the pdf of an executed contract', function () {
    $contract = executedContractWithPdf($this->team);

    actingAs($this->user)
        ->get(route('contracts.download', $contract))
        ->assertOk()
        ->assertDownload('contract.pdf');
});

it('does not download a contract that has not been executed', function () {
    $contract = Contract::factory()->for($this->team)->create();

    actingAs($this->user)
        ->get(route('contracts.download', $contract))
        ->assertNotFound();
});

it('does not download a contract without a pdf', function () {
    $contract = Contract::factory()->for($this->team)->executed()->create();

    actingAs($this->user)
        ->get(route('contracts.download', $contract))
        ->assertNotFound();
});

it('does not download a contract when the pdf file is missing', function () {
    Storage::fake(config('picstome.disk'));

    $contract = Contract::factory()->for($this->team)->executed()->create([
        'pdf_file_path' => 'contracts/1/missing.pdf',
    ]);

    actingAs($this->user)
        ->get(route('contracts.download', $contract))
        ->assertNotFound();
});

it('does not download the contract of another team', function () {
    $contract = executedContractWithPdf(Team::factory()->create());

    actingAs($this->user)
        ->get(route('contracts.download', $contract))
        ->assertForbidden();
});

it('redirects guests to the login page', function () {
    $contract = executedContractWithPdf($this->team);

    get(route('contracts.download', $contract))->assertRedirect('/login');
});
