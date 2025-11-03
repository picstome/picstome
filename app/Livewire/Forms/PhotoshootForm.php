<?php

namespace App\Livewire\Forms;

use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PhotoshootForm extends Form
{
    public ?Photoshoot $photoshoot;

    public $name;

    public $customerName;

    public $customerEmail;

    public $date;

    public $price;

    public $location;

    public $comment;

    public $customer;

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

    protected function team()
    {
        return Auth::user()->currentTeam;
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'customerName' => 'required_without:customer',
            'customerEmail' => [
                'nullable',
                'email',
                'required_without:customer',
                Rule::unique('customers', 'email')->where(function ($query) {
                    return $query->where('team_id', $this->team()->id);
                }),
            ],
            'date' => 'nullable|date',
            'price' => 'nullable|integer',
            'location' => 'nullable',
            'comment' => 'nullable',
            'customer' => [
                'nullable',
                'exists:customers,id,team_id,'.$this->team()->id,
            ],
        ];
    }

    public function store()
    {
        $this->validate();

        if (! $this->customer) {
            $customer = $this->team()->customers()->create([
                'name' => $this->customerName,
                'email' => $this->customerEmail,
            ]);
        }

        return $this->team()->photoshoots()->create([
            'name' => $this->name,
            'customer_id' => $this->customer ?? $customer->id,
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
