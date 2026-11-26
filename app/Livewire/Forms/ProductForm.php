<?php

namespace App\Livewire\Forms;

use App\Enums\DepositType;
use App\Livewire\Forms\Concerns\NormalizesMoneyInput;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * Create and edit a bookable product.
 *
 * Prices are shown in euros but stored in minor units, like payments and
 * invoices. A null deposit type means "use the team default".
 */
class ProductForm extends Form
{
    use NormalizesMoneyInput;

    public ?int $product_id = null;

    public string $name = '';

    public string $description = '';

    public string $duration_minutes = '60';

    public string $price = '0.00';

    public string $deposit_type = '';

    public string $deposit_value = '';

    public string $buffer_minutes = '';

    public string $location_name = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $light_mode = 'none';

    public string $color = '';

    public bool $is_active = true;

    public string $sort_order = '0';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'deposit_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'deposit_value' => ['nullable', 'required_if:deposit_type,fixed,percent', 'numeric', 'min:0'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'light_mode' => ['required', Rule::in(['none', 'sunset', 'sunrise'])],
            'color' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function setProduct(Product $product): void
    {
        $this->product_id = $product->id;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->duration_minutes = (string) $product->duration_minutes;
        $this->price = number_format($product->price / 100, 2, '.', '');
        $this->deposit_type = $product->deposit_type instanceof DepositType
            ? $product->deposit_type->value
            : '';
        $this->deposit_value = $product->deposit_value === null
            ? ''
            : ($product->deposit_type === DepositType::Percent
                ? (string) $product->deposit_value
                : number_format($product->deposit_value / 100, 2, '.', ''));
        $this->buffer_minutes = $product->buffer_minutes === null ? '' : (string) $product->buffer_minutes;
        $this->location_name = (string) $product->location_name;
        $this->latitude = $product->latitude === null ? '' : (string) $product->latitude;
        $this->longitude = $product->longitude === null ? '' : (string) $product->longitude;
        $this->light_mode = $product->light_mode->value;
        $this->color = (string) $product->color;
        $this->is_active = (bool) $product->is_active;
        $this->sort_order = (string) $product->sort_order;
    }

    public function save(Team $team): Product
    {
        $this->price = $this->normalizeDecimalInput($this->price);
        $this->deposit_value = $this->normalizeDecimalInput($this->deposit_value);

        $this->validate();

        $depositType = $this->deposit_type !== '' ? $this->deposit_type : null;

        if ($depositType === 'percent' && (float) $this->deposit_value > 100) {
            throw ValidationException::withMessages([
                'deposit_value' => __('The deposit percentage cannot be higher than 100.'),
            ]);
        }

        $depositValue = match ($depositType) {
            null, 'none' => null,
            'percent' => (int) $this->deposit_value,
            'fixed' => (int) round((float) $this->deposit_value * 100),
            default => null,
        };

        $data = [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'duration_minutes' => (int) $this->duration_minutes,
            'price' => (int) round((float) $this->price * 100),
            'deposit_type' => $depositType,
            'deposit_value' => $depositValue,
            'buffer_minutes' => $this->buffer_minutes === '' ? null : (int) $this->buffer_minutes,
            'location_name' => $this->location_name !== '' ? $this->location_name : null,
            'latitude' => $this->latitude === '' ? null : (float) $this->latitude,
            'longitude' => $this->longitude === '' ? null : (float) $this->longitude,
            'light_mode' => $this->light_mode,
            'color' => $this->color !== '' ? $this->color : null,
            'is_active' => $this->is_active,
        ];

        if ($this->product_id !== null) {
            $product = $team->products()->findOrFail($this->product_id);
            $product->update($data + ['sort_order' => (int) $this->sort_order]);

            return $product;
        }

        return $team->products()->create($data + [
            'currency' => $team->stripe_currency ?: 'EUR',
            'sort_order' => (int) $team->products()->max('sort_order') + 1,
        ]);
    }

    public function resetForm(): void
    {
        $this->reset([
            'product_id', 'name', 'description', 'duration_minutes', 'price',
            'deposit_type', 'deposit_value', 'buffer_minutes', 'location_name',
            'latitude', 'longitude', 'color', 'sort_order',
        ]);

        $this->duration_minutes = '60';
        $this->price = '0.00';
        $this->light_mode = 'none';
        $this->color = '';
        $this->is_active = true;
        $this->sort_order = '0';
        $this->resetErrorBag();
    }
}
