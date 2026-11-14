<?php

namespace App\Livewire\Forms;

use App\Models\Photoshoot;
use App\Services\PaymentPlanLimitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    #[Validate('nullable|required_if:booking,true|date')]
    public ?string $booking_date = null;

    #[Validate('nullable|required_if:booking,true|date_format:H:i')]
    public ?string $booking_start_time = null;

    #[Validate('nullable|required_if:booking,true|date_format:H:i')]
    public ?string $booking_end_time = null;

    #[Validate('nullable|required_if:booking,true|string|max:64')]
    public ?string $timezone = null;

    public function setPhotoshoot(Photoshoot $photoshoot): void
    {
        $this->photoshoot = $photoshoot;

        $this->description = $this->photoshoot->name;
    }

    public function generatePaymentLink(): string
    {
        $this->booking_start_time = $this->normalizeTime($this->booking_start_time);
        $this->booking_end_time = $this->normalizeTime($this->booking_end_time);

        $this->validate();

        $team = Auth::user()->currentTeam;

        if (! app(PaymentPlanLimitService::class)->canReceive($team)) {
            throw ValidationException::withMessages([
                'amount' => __('You have reached the monthly payment limit. Upgrade your plan to receive more payments.'),
            ]);
        }

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
                'timezone' => $this->timezone,
            ] : []),
        ], false);

        $shortDomain = config('picstome.short_url_domain');

        return rtrim($shortDomain, '/').$relativePath;
    }

    /**
     * Accept both 24-hour ("14:30") and 12-hour ("2:30 PM") values and store
     * them as H:i, the format the booking validation and the pay page expect.
     * The time picker used to emit 12-hour values, which made the link fail
     * validation even though the UI showed a valid time.
     */
    protected function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return null;
        }

        foreach (['H:i', 'H:i:s', 'h:i A', 'h:iA', 'g:i A', 'g:iA'] as $format) {
            if (Carbon::hasFormat($time, $format)) {
                return Carbon::createFromFormat($format, $time)->format('H:i');
            }
        }

        return $time;
    }
}
