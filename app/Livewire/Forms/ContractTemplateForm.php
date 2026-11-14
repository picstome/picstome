<?php

namespace App\Livewire\Forms;

use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Auth;
use League\HTMLToMarkdown\HtmlConverter;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ContractTemplateForm extends Form
{
    public ?ContractTemplate $contractTemplate;

    #[Validate('required')]
    public $title;

    #[Validate('required')]
    public $body;

    #[Validate('boolean')]
    public $isPublic = false;

    #[Validate(['nullable', 'string', 'in:en,es'])]
    public $locale;

    public function setContractTemplate(ContractTemplate $contractTemplate)
    {
        $this->contractTemplate = $contractTemplate;

        $this->title = $contractTemplate->title;

        $this->body = $contractTemplate->formatted_markdown_body;

        $this->isPublic = $contractTemplate->isPublic();

        $this->locale = $contractTemplate->locale ?? app()->getLocale();
    }

    public function store()
    {
        $this->validate();

        $this->ensureUserCanPublish();

        return ContractTemplate::create([
            'team_id' => $this->isPublic ? null : Auth::user()->currentTeam->id,
            'title' => $this->title,
            'markdown_body' => $this->convertBodyToMarkdown(),
            'locale' => $this->isPublic ? $this->resolvedLocale() : null,
        ]);
    }

    public function update()
    {
        $this->validate();

        $this->ensureUserCanPublish();

        $attributes = [
            'title' => $this->title,
            'markdown_body' => $this->convertBodyToMarkdown(),
        ];

        // Only admins may change who owns a template. Team members editing
        // their own template leave `team_id` and `locale` untouched.
        if (Auth::user()->is_admin) {
            $attributes['team_id'] = $this->isPublic
                ? null
                : ($this->contractTemplate->team_id ?? Auth::user()->currentTeam->id);
            $attributes['locale'] = $this->isPublic ? $this->resolvedLocale() : null;
        }

        return $this->contractTemplate->update($attributes);
    }

    protected function ensureUserCanPublish(): void
    {
        abort_if($this->isPublic && ! Auth::user()->is_admin, 403);
    }

    protected function resolvedLocale(): string
    {
        $locales = config('translation_overrides.locales', ['en', 'es']);

        return in_array($this->locale, $locales, true) ? $this->locale : app()->getLocale();
    }

    protected function convertBodyToMarkdown()
    {
        return (new HtmlConverter(['header_style' => 'atx', 'strip_tags' => true]))
            ->convert($this->body);
    }
}
