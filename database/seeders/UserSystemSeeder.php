<?php

namespace Database\Seeders;

use App\Models\UserSystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'names' => 'User',
                'last_name' => 'SAC 1',
                'email' => 'user_sac1@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000002',
                'names' => 'User',
                'last_name' => 'SAC 2',
                'email' => 'user_sac2@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000003',
                'names' => 'User',
                'last_name' => 'SAC 3',
                'email' => 'user_sac3@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000004',
                'names' => 'User',
                'last_name' => 'SAC 4',
                'email' => 'user_sac4@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000005',
                'names' => 'User',
                'last_name' => 'SAC 5',
                'email' => 'user_sac5@example.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $userData) {
            UserSystem::create($userData);
        }

        $this->command->info('Created '.count($users).' system users');
    }
}
