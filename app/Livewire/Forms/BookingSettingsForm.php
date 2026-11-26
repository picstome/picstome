<?php

namespace App\Livewire\Forms;

use App\Enums\DepositType;
use App\Livewire\Forms\Concerns\NormalizesMoneyInput;
use App\Models\AvailabilityWindow;
use App\Models\BookingSetting;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * Weekly schedule, booking guards and payment defaults of a team.
 *
 * The form replaces the whole week at once, so the "copy to all" action is
 * just a local change and the save is a single transaction.
 */
class BookingSettingsForm extends Form
{
    use NormalizesMoneyInput;

    public int $team_id = 0;

    public string $timezone = 'Europe/Madrid';

    public string $slot_step_minutes = '30';

    public string $buffer_minutes = '15';

    public string $min_notice_hours = '24';

    public string $horizon_days = '90';

    public bool $phone_required = false;

    public string $max_sessions_per_day = '';

    public string $default_deposit_type = 'none';

    public string $default_deposit_value = '0';

    public string $cancellation_policy = '';

    public bool $auto_issue_invoices = false;

    public bool $reminder_first_enabled = true;

    public string $reminder_first_hours = '72';

    public bool $reminder_second_enabled = true;

    public string $reminder_second_hours = '24';

    public bool $bizum_enabled = false;

    public bool $card_enabled = true;

    public string $bizum_phone = '';

    public string $bizum_instructions = '';

    /**
     * One row per ISO weekday (1 = Monday, 7 = Sunday).
     *
     * @var array<int, array{enabled: bool, start: string, end: string}>
     */
    public array $windows = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'timezone'],
            'slot_step_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'phone_required' => ['boolean'],
            'max_sessions_per_day' => ['nullable', 'integer', 'min:1', 'max:50'],
            'default_deposit_type' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'default_deposit_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'auto_issue_invoices' => ['boolean'],
            'reminder_first_enabled' => ['boolean'],
            'reminder_first_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'reminder_second_enabled' => ['boolean'],
            'reminder_second_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'bizum_enabled' => ['boolean'],
            'card_enabled' => [
                'boolean',
                function (string $attribute, mixed $value, $fail): void {
                    if (! $value && ! $this->bizum_enabled) {
                        $fail(__('Enable at least one payment method.'));
                    }
                },
            ],
            'bizum_phone' => ['nullable', 'required_if:bizum_enabled,true', 'string', 'max:32'],
            'bizum_instructions' => ['nullable', 'string', 'max:2000'],
            'windows' => ['array'],
            'windows.*.enabled' => ['boolean'],
            'windows.*.start' => ['nullable', 'date_format:H:i'],
            'windows.*.end' => [
                'nullable',
                'date_format:H:i',
                function (string $attribute, mixed $value, $fail): void {
                    $weekday = (int) explode('.', $attribute)[1];
                    $window = $this->windows[$weekday] ?? null;

                    if (! is_array($window) || ! ($window['enabled'] ?? false)) {
                        return;
                    }

                    if (($window['start'] ?? '') === '' || ! is_string($value) || $value === '') {
                        $fail(__('Both times are required for an open day.'));

                        return;
                    }

                    if ($value <= $window['start']) {
                        $fail(__('The end time must be later than the start time.'));
                    }
                },
            ],
        ];
    }

    public function setTeam(Team $team): void
    {
        $this->team_id = $team->id;

        $settings = BookingSetting::forTeam($team);

        $this->timezone = (string) $settings->timezone;
        $this->slot_step_minutes = (string) $settings->slot_step_minutes;
        $this->buffer_minutes = (string) $settings->buffer_minutes;
        $this->min_notice_hours = (string) $settings->min_notice_hours;
        $this->horizon_days = (string) $settings->horizon_days;
        $this->phone_required = (bool) $settings->phone_required;
        $this->max_sessions_per_day = $settings->max_sessions_per_day === null
            ? ''
            : (string) $settings->max_sessions_per_day;
        $this->default_deposit_type = $settings->default_deposit_type->value;
        $this->default_deposit_value = $settings->default_deposit_type === DepositType::Fixed
            ? number_format($settings->default_deposit_value / 100, 2, '.', '')
            : (string) $settings->default_deposit_value;
        $this->cancellation_policy = (string) $settings->cancellation_policy;
        $this->auto_issue_invoices = (bool) $settings->auto_issue_invoices;
        $this->reminder_first_enabled = (bool) $settings->reminder_first_enabled;
        $this->reminder_first_hours = (string) $settings->reminder_first_hours;
        $this->reminder_second_enabled = (bool) $settings->reminder_second_enabled;
        $this->reminder_second_hours = (string) $settings->reminder_second_hours;
        $this->bizum_enabled = (bool) $settings->bizum_enabled;
        $this->card_enabled = (bool) $settings->card_enabled;
        $this->bizum_phone = (string) $settings->bizum_phone;
        $this->bizum_instructions = (string) $settings->bizum_instructions;

        $this->windows = $this->windowsFor($team);
    }

    /**
     * Replace the weekly schedule with the values of the form.
     */
    public function save(Team $team): void
    {
        $this->default_deposit_value = $this->normalizeDecimalInput($this->default_deposit_value);

        $this->validate();

        if ($this->default_deposit_type === 'percent' && (float) $this->default_deposit_value > 100) {
            throw ValidationException::withMessages([
                'default_deposit_value' => __('The deposit percentage cannot be higher than 100.'),
            ]);
        }

        $depositValue = match (DepositType::from($this->default_deposit_type)) {
            DepositType::None => 0,
            DepositType::Percent => (int) $this->default_deposit_value,
            DepositType::Fixed => (int) round((float) $this->default_deposit_value * 100),
        };

        DB::transaction(function () use ($team, $depositValue) {
            BookingSetting::forTeam($team)->update([
                'timezone' => $this->timezone,
                'slot_step_minutes' => (int) $this->slot_step_minutes,
                'buffer_minutes' => (int) $this->buffer_minutes,
                'min_notice_hours' => (int) $this->min_notice_hours,
                'horizon_days' => (int) $this->horizon_days,
                'max_sessions_per_day' => $this->max_sessions_per_day === ''
                    ? null
                    : (int) $this->max_sessions_per_day,
                'phone_required' => $this->phone_required,
                'default_deposit_type' => $this->default_deposit_type,
                'default_deposit_value' => $depositValue,
                'cancellation_policy' => $this->cancellation_policy !== '' ? $this->cancellation_policy : null,
                'auto_issue_invoices' => $this->auto_issue_invoices,
                'reminder_first_enabled' => $this->reminder_first_enabled,
                'reminder_first_hours' => (int) $this->reminder_first_hours,
                'reminder_second_enabled' => $this->reminder_second_enabled,
                'reminder_second_hours' => (int) $this->reminder_second_hours,
                'card_enabled' => $this->card_enabled,
                'bizum_enabled' => $this->bizum_enabled,
                'bizum_phone' => $this->bizum_phone !== '' ? $this->bizum_phone : null,
                'bizum_instructions' => $this->bizum_instructions !== '' ? $this->bizum_instructions : null,
            ]);

            $team->availabilityWindows()->delete();

            foreach ($this->windows as $weekday => $window) {
                if (! ($window['enabled'] ?? false)) {
                    continue;
                }

                AvailabilityWindow::create([
                    'team_id' => $team->id,
                    'weekday' => $weekday,
                    'start_time' => $window['start'].':00',
                    'end_time' => $window['end'].':00',
                ]);
            }
        });
    }

    /**
     * Copy Monday's window to every other day.
     */
    public function copyMondayToAll(): void
    {
        $monday = $this->windows[1];

        foreach (array_keys($this->windows) as $weekday) {
            if ($weekday === 1) {
                continue;
            }

            $this->windows[$weekday] = $monday;
        }
    }

    /**
     * @return array<int, array{enabled: bool, start: string, end: string}>
     */
    private function windowsFor(Team $team): array
    {
        $windows = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $windows[$weekday] = [
                'enabled' => $weekday <= 5,
                'start' => '09:00',
                'end' => '18:00',
            ];
        }

        foreach ($team->availabilityWindows()->get() as $window) {
            $windows[(int) $window->weekday] = [
                'enabled' => true,
                'start' => Carbon::parse($window->start_time)->format('H:i'),
                'end' => Carbon::parse($window->end_time)->format('H:i'),
            ];
        }

        ksort($windows);

        return $windows;
    }
}
