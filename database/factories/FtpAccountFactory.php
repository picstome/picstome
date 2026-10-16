<?php

namespace Database\Factories;

use App\Models\FtpAccount;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FtpAccount> */
class FtpAccountFactory extends Factory
{
    protected $model = FtpAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'username' => FtpAccount::generateCode(),
            'password' => FtpAccount::generateNumericPassword(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
