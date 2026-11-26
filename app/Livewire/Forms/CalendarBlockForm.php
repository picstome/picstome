<?php

namespace App\Livewire\Forms;

use App\Models\CalendarBlock;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Livewire\Form;

/**
 * A holiday or a partial day the photographer is unavailable.
 *
 * All-day blocks may span several days, and so may partial ones: a trip from
 * Friday 20:00 to Monday 18:00 is a single continuous block. Only the order
 * of the instants matters.
 */
class CalendarBlockForm extends Form
{
    public string $starts_on = '';

    public string $ends_on = '';

    public bool $all_day = true;

    public string $start_time = '09:00';

    public string $end_time = '10:00';

    public string $reason = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'all_day' => ['boolean'],
            'start_time' => ['nullable', 'required_if:all_day,false', 'date_format:H:i'],
            'end_time' => [
                'nullable',
                'required_if:all_day,false',
                'date_format:H:i',
                function (string $attribute, mixed $value, $fail): void {
                    if ($this->all_day || ! is_string($value) || $value === '') {
                        return;
                    }

                    // A different end date is always later, whatever the time.
                    if ($this->starts_on !== $this->ends_on) {
                        return;
                    }

                    if ($value <= $this->start_time) {
                        $fail(__('The end time must be later than the start time.'));
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function setDate(string $date): void
    {
        $this->starts_on = $date;
        $this->ends_on = $date;
        $this->all_day = true;
        $this->reason = '';
    }

    public function save(Team $team, string $timezone): CalendarBlock
    {
        $this->validate();

        if ($this->all_day) {
            $startsAt = CarbonImmutable::parse($this->starts_on, $timezone)->startOfDay();
            $endsAt = CarbonImmutable::parse($this->ends_on, $timezone)->endOfDay();
        } else {
            $startsAt = CarbonImmutable::parse($this->starts_on.' '.$this->start_time, $timezone);
            $endsAt = CarbonImmutable::parse($this->ends_on.' '.$this->end_time, $timezone);
        }

        return $team->calendarBlocks()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $this->all_day,
            'reason' => $this->reason !== '' ? $this->reason : null,
        ]);
    }

    public function resetForm(): void
    {
        $this->reset(['starts_on', 'ends_on', 'start_time', 'end_time', 'reason']);
        $this->all_day = true;
        $this->start_time = '09:00';
        $this->end_time = '10:00';
        $this->resetErrorBag();
    }
}
