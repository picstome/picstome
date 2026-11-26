<?php

namespace Database\Factories;

use App\Enums\DepositType;
use App\Enums\LightMode;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'description' => null,
            'duration_minutes' => 60,
            'price' => 15000,
            'currency' => 'EUR',
            'deposit_type' => null,
            'deposit_value' => null,
            'buffer_minutes' => null,
            'location_name' => null,
            'latitude' => null,
            'longitude' => null,
            'light_mode' => LightMode::None,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function duration(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_minutes' => $minutes,
        ]);
    }

    public function price(int $minorUnits): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $minorUnits,
        ]);
    }

    public function deposit(DepositType $type, int $value): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit_type' => $type,
            'deposit_value' => $value,
        ]);
    }

    public function sunset(string $location = 'Playa', float $latitude = 39.5696, float $longitude = 2.6502): static
    {
        return $this->state(fn (array $attributes) => [
            'light_mode' => LightMode::Sunset,
            'location_name' => $location,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
