<?php

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

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
        $component = Livewire::test('pages::contracts');

        $response->assertStatus(200);

        expect($component->contracts->count())->toBe(2);
        expect($component->contracts->contains($contractA))->toBeTrue();
        expect($component->contracts->contains($contractB))->toBeFalse();
        expect($component->contracts->contains($contractC))->toBeTrue();
    });
});

describe('Creating contracts', function () {
    it('allows a user to add a new contract with all required fields', function () {
        $component = Livewire::actingAs($this->user)->test('pages::contracts')
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
    it('enforces monthly contract limit for non-subscribed teams', function () {
        $this->team->update([
            'monthly_contract_limit' => 5,
        ]);

        Contract::factory()->count(5)->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::contracts')
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

    it('ignores contracts from previous months when enforcing monthly limit', function () {
        $this->team->update([
            'monthly_contract_limit' => 5,
        ]);

        Contract::factory()->count(5)->for($this->team)->create([
            'created_at' => Carbon::now()->subMonth()->startOfMonth(),
        ]);

        Contract::factory()->count(4)->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::contracts')
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

    it('skips monthly limit for teams with unlimited contracts', function () {
        $this->team->update([
            'monthly_contract_limit' => null, // null
        ]);

        Contract::factory()->count(6)->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::contracts')
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

    it('skips monthly limit for subscribed teams', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        expect($this->user->currentTeam->subscribed())->toBeTrue();
        Contract::factory()->count(6)->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::contracts')
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
