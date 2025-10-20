<?php

namespace App\Livewire\Forms;

use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PaymentLinkForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    #[Validate('required|integer|min:1')]
    public $amount;

    #[Validate('required|string|max:255')]
    public $description;

    #[Validate('boolean')]
    public bool $booking = false;

    #[Validate('required_if:booking,true|date')]
    public ?string $booking_date = null;

    #[Validate('required_if:booking,true|date_format:H:i')]
    public ?string $booking_start_time = null;

    #[Validate('required_if:booking,true|date_format:H:i')]
    public ?string $booking_end_time = null;

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;

        $this->description = $this->photoshoot->name;
    }

    public function generatePaymentLink()
    {
        $this->validate();

        $relativePath = route('handle.pay', [
            'handle' => Auth::user()->currentTeam->handle,
            'amount' => $this->amount,
            'description' => $this->description,
            'photoshoot_id' => $this->photoshoot?->id ?? null,
            ...($this->booking ? [
                'booking' => true,
                'booking_date' => $this->booking_date,
                'booking_start_time' => $this->booking_start_time,
                'booking_end_time' => $this->booking_end_time,
            ] : []),
        ], false);

        $shortDomain = config('picstome.short_url_domain');

        return rtrim($shortDomain, '/').$relativePath;
    }
}
