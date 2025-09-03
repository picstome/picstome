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
        // User::factory(10)->create();

        $this->createUserWithHandle('Test User', 'test@example.com', 'testuser');
        $this->createUserWithHandle('Oliver', 'oliver@example.com', 'oliver');
        $this->createUserWithHandle('Chema', 'chema@example.com', 'chema');
    }

    private function createUserWithHandle(string $name, string $email, string $handle): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $user->currentTeam->update(['handle' => $handle]);
    }
}
