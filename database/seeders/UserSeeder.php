<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bator = User::query()->firstOrCreate(
            ['email' => 'gilbertttsimbolon@gmail.com'],
            [
                'name' => 'Bator',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $bator->assignRole('Admin');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $admin->assignRole('Admin');

        $agent = User::query()->updateOrCreate(
            ['email' => 'agent@example.com'],
            [
                'name' => 'Agent',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ]
        );
        $agent->assignRole('Agent');
    }
}
