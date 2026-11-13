<?php

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
    it("shows only contracts belonging to user's team", function () {
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

    it('links to contract templates from the page header', function () {
        actingAs($this->user)
            ->get('/contracts')
            ->assertOk()
            ->assertSee(route('contract-templates'), false);
    });

    it('shows a download button for executed contracts with a pdf', function () {
        Storage::fake(config('picstome.disk'));

        $executed = Contract::factory()->for($this->team)->executed()->create([
            'pdf_file_path' => 'contracts/1/contract.pdf',
        ]);
        $pending = Contract::factory()->for($this->team)->create();

        actingAs($this->user)
            ->get('/contracts')
            ->assertOk()
            ->assertSee(route('contracts.download', $executed), false)
            ->assertDontSee(route('contracts.download', $pending), false);
    });
});

describe('Contract templates', function () {
    it('lists team templates before Picstome templates in the picker', function () {
        ContractTemplate::factory()->for($this->team)->create(['title' => 'My release']);
        ContractTemplate::factory()->create([
            'team_id' => null,
            'locale' => 'en',
            'title' => 'Picstome release',
        ]);
        ContractTemplate::factory()->create([
            'team_id' => null,
            'locale' => 'es',
            'title' => 'Plantilla en español',
        ]);

        Livewire::actingAs($this->user)->test('pages::contracts')
            ->assertSeeInOrder([
                __('My templates'),
                'My release',
                __('Picstome contract templates'),
                'Picstome release',
            ])
            ->assertDontSee('Plantilla en español')
            ->assertSee(__('Choose a template to start from.'));
    });

    it('fills the contract body from a Picstome template and shows the warning', function () {
        $template = ContractTemplate::factory()->create([
            'team_id' => null,
            'locale' => 'en',
            'markdown_body' => '### Shared body',
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::contracts')
            ->call('useTemplate', $template->id)
            ->assertSet('usingPublicTemplate', true)
            ->assertSee(__('You are using a Picstome template. Review and adapt it before saving. It is not legal advice.'));

        expect($component->form->body)->toContain('<h3>Shared body</h3>');
    });

    it('does not show the warning after using a team template', function () {
        $template = ContractTemplate::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::contracts')
            ->call('useTemplate', $template->id)
            ->assertSet('usingPublicTemplate', false)
            ->assertDontSee(__('You are using a Picstome template. Review and adapt it before saving. It is not legal advice.'));
    });

    it('does not use a private template from another team', function () {
        $template = ContractTemplate::factory()->for(Team::factory())->create();

        Livewire::actingAs($this->user)->test('pages::contracts')
            ->call('useTemplate', $template->id)
            ->assertStatus(403);
    });

    test('shows the Picstome warning after picking a public template', function () {
        ContractTemplate::factory()->create([
            'team_id' => null,
            'locale' => 'en',
            'title' => 'Picstome release',
            'markdown_body' => '### Picstome body',
        ]);

        $this->actingAs($this->user);

        $page = visit('/contracts');

        $page->click('Create contract')
            ->click('Use template')
            ->select('[x-model="template"]', 'Picstome release')
            ->assertSee('This template is a starting point, not legal advice.')
            ->click('[data-template-picker-submit]')
            ->assertSee('You are using a Picstome template. Review and adapt it before saving. It is not legal advice.')
            ->assertNoJavaScriptErrors();
    })->group('browser');
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
            'created_at' => Carbon::now()->subMonthsNoOverflow(1)->startOfMonth(),
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
