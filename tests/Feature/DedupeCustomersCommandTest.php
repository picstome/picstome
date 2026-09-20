<?php

use App\Models\Customer;
use App\Models\Photoshoot;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function seedCristinaDuplicates(Team $team): array
{
    $cristina = Customer::factory()->for($team)->create([
        'name' => 'Cristina',
        'email' => 'c@x.com',
        'phone' => null,
        'birthdate' => '1990-05-10',
        'notes' => null,
    ]);
    Photoshoot::factory()->for($team)->create(['customer_id' => $cristina->id]);

    $requena = Customer::factory()->for($team)->create([
        'name' => 'Cristina Requena',
        'email' => 'c@x.com',
        'phone' => null,
        'birthdate' => '1990-05-10',
        'notes' => null,
    ]);

    return [$cristina, $requena];
}

it('dry run reports duplicate groups without changing the database', function () {
    $team = Team::factory()->create();
    [$cristina, $requena] = seedCristinaDuplicates($team);

    artisan('customers:dedupe')
        ->expectsOutputToContain("keep #{$cristina->id} (Cristina), merge #{$requena->id} (Cristina Requena)")
        ->expectsOutputToContain('Dry run only. Pass --execute to apply the merges.')
        ->assertSuccessful();

    expect(Customer::count())->toBe(2);
    expect($cristina->fresh()->name)->toBe('Cristina');
    expect(Photoshoot::first()->customer_id)->toBe($cristina->id);
});

it('execute merges duplicates into the canonical customer', function () {
    $team = Team::factory()->create();
    [$cristina, $requena] = seedCristinaDuplicates($team);

    artisan('customers:dedupe', ['--execute' => true])->assertSuccessful();

    expect(Customer::count())->toBe(1);

    $kept = Customer::first();
    expect($kept->id)->toBe($cristina->id);
    expect($kept->name)->toBe('Cristina Requena');
    expect($kept->email)->toBe('c@x.com');
    expect($kept->birthdate->format('Y-m-d'))->toBe('1990-05-10');
    expect(Customer::find($requena->id))->toBeNull();
    expect(Photoshoot::first()->customer_id)->toBe($cristina->id);
});

it('execute backfills null fields from duplicates', function () {
    $team = Team::factory()->create();
    [$cristina, $requena] = seedCristinaDuplicates($team);
    $requena->update([
        'phone' => '+34 600 111 222',
        'notes' => 'Prefers WhatsApp',
    ]);

    artisan('customers:dedupe', ['--execute' => true])->assertSuccessful();

    $kept = Customer::first();
    expect($kept->id)->toBe($cristina->id);
    expect($kept->phone)->toBe('+34 600 111 222');
    expect($kept->notes)->toBe('Prefers WhatsApp');
});

it('customers with null or distinct emails are never touched', function () {
    $team = Team::factory()->create();
    Customer::factory()->for($team)->create(['email' => null]);
    Customer::factory()->for($team)->create(['email' => null]);
    Customer::factory()->for($team)->create(['email' => 'a@x.com']);
    Customer::factory()->for($team)->create(['email' => 'b@x.com']);

    artisan('customers:dedupe', ['--execute' => true])
        ->expectsOutputToContain('No duplicate customers found.')
        ->assertSuccessful();

    expect(Customer::count())->toBe(4);
});

it('groups of three collapse to one', function () {
    $team = Team::factory()->create();

    $oldest = Customer::factory()->for($team)->create([
        'name' => 'Ana',
        'email' => 'ana@x.com',
        'birthdate' => '1985-01-01',
    ]);
    $middle = Customer::factory()->for($team)->create([
        'name' => 'Ana Garcia',
        'email' => 'ana@x.com',
        'birthdate' => '1985-01-01',
    ]);
    Photoshoot::factory()->for($team)->create(['customer_id' => $middle->id]);
    $youngest = Customer::factory()->for($team)->create([
        'name' => 'Ana',
        'email' => 'ana@x.com',
        'birthdate' => '1985-01-01',
    ]);

    artisan('customers:dedupe', ['--execute' => true])->assertSuccessful();

    expect(Customer::count())->toBe(1);

    $kept = Customer::first();
    expect($kept->id)->toBe($middle->id);
    expect($kept->name)->toBe('Ana Garcia');
    expect(Photoshoot::first()->customer_id)->toBe($middle->id);
    expect(Customer::find($oldest->id))->toBeNull();
    expect(Customer::find($youngest->id))->toBeNull();
});
