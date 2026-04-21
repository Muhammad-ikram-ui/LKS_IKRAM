<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'username' => 'admin1',
            'password' => bcrypt('hellouniverse1!'),
            'role' => 'admin',
            'last_login_at' => now(),
        ]);

        \App\Models\User::create([
            'username' => 'admin2',
            'password' => bcrypt('hellouniverse2!'),
            'role' => 'admin',
        ]);

        \App\Models\User::create([
            'username' => 'player1',
            'password' => bcrypt('helloworld1!'),
            'role' => 'player',
        ]);

        \App\Models\User::create([
            'username' => 'player2',
            'password' => bcrypt('helloworld2!'),
            'role' => 'player',
            'last_login_at' => now(),
        ]);
    }
}
