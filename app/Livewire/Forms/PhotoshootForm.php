<?php

namespace App\Livewire\Forms;

use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PhotoshootForm extends Form
{
    public ?Photoshoot $photoshoot;

    #[Validate('required')]
    public $name;

    #[Validate('required')]
    public $customerName;

    #[Validate('nullable|email')]
    public $customerEmail;

    #[Validate('nullable|date')]
    public $date;

    #[Validate('nullable|integer')]
    public $price;

    #[Validate('nullable')]
    public $location;

    #[Validate('nullable')]
    public $comment;

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;

        $this->name = $photoshoot->name;

        $this->customerName = $photoshoot->customer_name;

        $this->date = $photoshoot->date?->isoFormat('YYYY-MM-DD');

        $this->price = $photoshoot->price;

        $this->location = $photoshoot->location;

        $this->comment = $photoshoot->comment;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->photoshoots()->create([
            'name' => $this->name,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'date' => $this->date,
            'price' => $this->price,
            'location' => $this->location,
            'comment' => $this->comment,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->photoshoot->update([
            'name' => $this->name,
            'customer_name' => $this->customerName,
            'date' => $this->date,
            'price' => $this->price,
            'location' => $this->location,
            'comment' => $this->comment,
        ]);
    }
}
