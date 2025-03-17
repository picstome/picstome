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

    public function setContractTemplate(ContractTemplate $contractTemplate)
    {
        $this->contractTemplate = $contractTemplate;

        $this->title = $contractTemplate->title;

        $this->body = $contractTemplate->formatted_markdown_body;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->contractTemplates()->create([
            'title' => $this->title,
            'markdown_body' => $this->convertBodyToMarkdown(),
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->contractTemplate->update([
            'title' => $this->title,
            'markdown_body' => $this->convertBodyToMarkdown(),
        ]);
    }

    protected function convertBodyToMarkdown()
    {
        return (new HtmlConverter(['header_style' => 'atx', 'strip_tags' => true]))
            ->convert($this->body);
    }
}
