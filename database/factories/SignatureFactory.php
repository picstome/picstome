<?php

namespace Database\Factories;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Signature>
 */
class SignatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
        ];
    }

    public function unsigned(): Factory
    {
        return $this->state(function () {
            return [
                'signed_at' => null,
            ];
        });
    }

    public function signed(): Factory
    {
        return $this->state(function () {
            return [
                'email' => 'john@example.com',
                'signed_at' => Carbon::now()->subHour(),
            ];
        });
    }
}
