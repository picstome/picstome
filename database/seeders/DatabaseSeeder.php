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

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Oliver',
            'email' => 'oliver@example.com',
        ]);

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Chema',
            'email' => 'chema@example.com',
        ]);
    }
}
