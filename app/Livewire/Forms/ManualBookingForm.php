<?php

namespace App\Livewire\Forms;

use App\Models\Booking;
use App\Models\Team;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Livewire\Form;

/**
 * A booking the photographer types in for a client who called or came in.
 *
 * The time comes from the same availability engine as the public page, so a
 * manual booking cannot overlap an existing session or a block.
 */
class ManualBookingForm extends Form
{
    public ?int $product_id = null;

    public string $date = '';

    public string $start_time = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function setDate(string $date): void
    {
        $this->date = $date;
        $this->start_time = '';
    }

    public function save(Team $team, BookingService $service, string $timezone): Booking
    {
        $this->validate();

        $product = $team->products()->active()->findOrFail((int) $this->product_id);

        $startsAt = CarbonImmutable::parse($this->date.' '.$this->start_time, $timezone);

        return $service->createManual(
            $team,
            $product,
            $startsAt,
            [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
            ],
            $this->notes !== '' ? $this->notes : null,
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'product_id', 'date', 'start_time', 'name', 'email', 'phone', 'notes',
        ]);

        $this->resetErrorBag();
    }
}
