<?php

namespace Database\Factories;

use App\Models\FtpAccount;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FtpAccount> */
class FtpAccountFactory extends Factory
{
    protected $model = FtpAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'username' => FtpAccount::generateCode(),
            'password' => FtpAccount::generateCode(10),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
