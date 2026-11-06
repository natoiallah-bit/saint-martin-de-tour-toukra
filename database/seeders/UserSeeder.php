<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'direction@saintmartin.td'],
            [
                'name' => 'Direction',
                'password' => Hash::make('MotDePasse123'), // ⚠️ à changer après la première connexion
                'role' => 'direction',
            ]
        );

        User::firstOrCreate(
            ['email' => 'secretariat@saintmartin.td'],
            [
                'name' => 'Secrétariat',
                'password' => Hash::make('MotDePasse123'),
                'role' => 'secretariat',
            ]
        );
    }
}
