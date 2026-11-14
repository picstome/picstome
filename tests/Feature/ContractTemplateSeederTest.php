<?php

use App\Models\ContractTemplate;
use Database\Seeders\ContractTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the default Picstome templates in both languages', function () {
    $this->seed(ContractTemplateSeeder::class);

    expect(ContractTemplate::query()->whereNull('team_id')->count())->toBe(8);

    foreach (['en', 'es'] as $locale) {
        $templates = ContractTemplate::query()
            ->whereNull('team_id')
            ->where('locale', $locale)
            ->orderBy('title')
            ->get();

        expect($templates)->toHaveCount(4);
        expect($templates->every(fn (ContractTemplate $template) => $template->isPublic()))->toBeTrue();
    }
});

it('does not duplicate the defaults when seeded again', function () {
    $this->seed(ContractTemplateSeeder::class);
    $this->seed(ContractTemplateSeeder::class);

    expect(ContractTemplate::query()->whereNull('team_id')->count())->toBe(8);
});

it('ships template bodies without images or autofill placeholders', function () {
    $this->seed(ContractTemplateSeeder::class);

    ContractTemplate::query()
        ->whereNull('team_id')
        ->each(function (ContractTemplate $template) {
            expect($template->markdown_body)
                ->not->toContain('<img')
                ->not->toContain('![')
                ->not->toContain('{{')
                ->not->toMatch('/\[[^\]]+\]/');
        });
});
