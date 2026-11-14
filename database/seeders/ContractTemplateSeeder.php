<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ContractTemplateSeeder extends Seeder
{
    /**
     * Default Picstome templates, shipped in both supported languages.
     *
     * The Markdown bodies live in `resources/contract-templates` and are plain
     * text on purpose: images would inflate every generated contract PDF.
     *
     * @var array<string, array<string, string>>
     */
    private const TEMPLATES = [
        'model-release' => [
            'en' => 'Model Release',
            'es' => 'Autorización de imagen del modelo',
        ],
        'property-release' => [
            'en' => 'Property Release',
            'es' => 'Autorización de propiedad',
        ],
        'tfp-agreement' => [
            'en' => 'TFP Agreement',
            'es' => 'Acuerdo TFP',
        ],
        'brand-usage-license' => [
            'en' => 'Brand Usage License',
            'es' => 'Licencia de uso para marcas',
        ],
    ];

    /**
     * Create missing defaults without overwriting templates that an admin has
     * already edited, so the seeder is safe to run on every deploy.
     */
    public function run(): void
    {
        foreach (self::TEMPLATES as $slug => $titles) {
            foreach ($titles as $locale => $title) {
                ContractTemplate::query()->firstOrCreate(
                    [
                        'team_id' => null,
                        'locale' => $locale,
                        'title' => $title,
                    ],
                    [
                        'markdown_body' => File::get(
                            resource_path("contract-templates/{$slug}.{$locale}.md")
                        ),
                    ],
                );
            }
        }
    }
}
