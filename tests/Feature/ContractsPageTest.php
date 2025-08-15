<?php

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Viewing contracts', function () {
    it('shows only contracts belonging to the user’s team', function () {
        $contractA = Contract::factory()->for($this->team)->create();
        $contractB = Contract::factory()->for(Team::factory())->create();
        $contractC = Contract::factory()->for($this->team)->create();

        $response = actingAs($this->user)->get('/contracts');
        $component = Volt::test('pages.contracts');

        $response->assertStatus(200);
        $component->assertViewHas('contracts');
        expect($component->viewData('contracts')->contains($contractA))->toBeTrue();
        expect($component->viewData('contracts')->contains($contractB))->toBeFalse();
        expect($component->viewData('contracts')->contains($contractC))->toBeTrue();
    });
});

describe('Creating contracts', function () {
    it('allows a user to add a new contract with all required fields', function () {
        $component = Volt::actingAs($this->user)->test('pages.contracts')
            ->set('form.title', 'A contract title')
            ->set('form.description', 'A contract description')
            ->set('form.location', 'A location')
            ->set('form.shootingDate', Carbon::parse('12-12-2025'))
            ->set('form.body', '<h3>Body in HTML</h3>')
            ->set('form.signature_quantity', 3)
            ->call('save');

        $component->assertRedirect('/contracts/1');
        expect(Contract::count())->toBe(1);
        tap(Contract::first(), function (Contract $contract) {
            expect($contract->title)->toBe('A contract title');
            expect($contract->description)->toBe('A contract description');
            expect($contract->location)->toBe('A location');
            expect((string) $contract->shooting_date)->toBe('2025-12-12 00:00:00');
            expect($contract->markdown_body)->toBe('### Body in HTML');
            expect($contract->signaturesRemaining())->toBe(3);
            $contract->signatures()->each(function ($contract) {
                expect($contract->isSigned())->toBeFalse();
            });
        });
    });

    it('prevents guests from creating contracts', function () {
        get('contracts')->assertRedirect('/login');
    });
});

describe('Contract limits', function () {
    it('limits contract creation to 5 for non-subscribed, non-unlimited teams in the current month', function () {
        $this->team->update([
            'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
        ]);

        Contract::factory()->count(5)->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.contracts')
            ->set('form.title', 'Contract 6')
            ->set('form.description', 'Desc')
            ->set('form.location', 'Loc')
            ->set('form.shootingDate', Carbon::parse('12-12-2025'))
            ->set('form.body', '<h3>Body</h3>')
            ->set('form.signature_quantity', 1)
            ->call('save');

        $component->assertStatus(403);
        expect($this->team->contracts()->count())->toBe(5);
    });

    it('does not count contracts from previous months toward the current month limit', function () {
        $this->team->update([
            'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
        ]);

        Contract::factory()->count(5)->for($this->team)->create([
            'created_at' => Carbon::now()->subMonth()->startOfMonth(),
        ]);

        Contract::factory()->count(4)->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.contracts')
            ->set('form.title', 'Contract 5 this month')
            ->set('form.description', 'Desc')
            ->set('form.location', 'Loc')
            ->set('form.shootingDate', Carbon::parse('12-12-2025'))
            ->set('form.body', '<h3>Body</h3>')
            ->set('form.signature_quantity', 1)
            ->call('save');

        $component->assertRedirect('/contracts/10');
        expect($this->team->contracts()->count())->toBe(10);
    });

    it('does not limit contract creation for teams with unlimited storage', function () {
        $this->team->update([
            'custom_storage_limit' => null, // unlimited
        ]);

        Contract::factory()->count(6)->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.contracts')
            ->set('form.title', 'Contract 7')
            ->set('form.description', 'Desc')
            ->set('form.location', 'Loc')
            ->set('form.shootingDate', Carbon::parse('12-12-2025'))
            ->set('form.body', '<h3>Body</h3>')
            ->set('form.signature_quantity', 1)
            ->call('save');

        $component->assertRedirect('/contracts/7');
        expect($this->team->contracts()->count())->toBe(7);
    });

    it('does not limit contract creation for teams with active subscription', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        expect($this->user->currentTeam->subscribed())->toBeTrue();
        Contract::factory()->count(6)->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.contracts')
            ->set('form.title', 'Contract 7')
            ->set('form.description', 'Desc')
            ->set('form.location', 'Loc')
            ->set('form.shootingDate', Carbon::parse('12-12-2025'))
            ->set('form.body', '<h3>Body</h3>')
            ->set('form.signature_quantity', 1)
            ->call('save');

        $component->assertRedirect('/contracts/7');
        expect($this->team->contracts()->count())->toBe(7);
    });
});
