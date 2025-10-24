<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->createUserWithHandle('Admin', 'admin@example.com', 'admin', 'admin');
    }

    private function createUserWithHandle(string $name, string $email, string $handle, string $password): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->currentTeam->update(['handle' => $handle]);
    }
}
