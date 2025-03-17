<?php

namespace App\Livewire\Forms;

use App\Models\Photoshoot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use League\HTMLToMarkdown\HtmlConverter;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ContractForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    #[Validate('required')]
    public $title;

    #[Validate('required')]
    public $description;

    #[Validate('required')]
    public $location;

    #[Validate('required')]
    public $shootingDate;

    #[Validate('required')]
    public $body;

    #[Validate('required|integer|min:1')]
    public $signature_quantity = 1;

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;

        $this->title = $photoshoot->name;

        $this->location = $photoshoot->location;

        $this->shootingDate = Carbon::now()->isoFormat('YYYY-MM-DD');
    }

    public function store()
    {
        $this->validate();

        $contract = Auth::user()->currentTeam->contracts()->create([
            'photoshoot_id' => $this->photoshoot?->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'shooting_date' => $this->shootingDate,
            'markdown_body' => $this->convertBodyToMarkdown(),
        ]);

        $contract->addSignatures($this->signature_quantity);

        return $contract;
    }

    protected function convertBodyToMarkdown()
    {
        return (new HtmlConverter(['header_style' => 'atx', 'strip_tags' => true]))
            ->convert($this->body);
    }
}
