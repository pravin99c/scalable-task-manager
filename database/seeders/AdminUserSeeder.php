<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. SUPERADMIN (Full Access)
        \App\Models\User::updateOrCreate(
            ['email' => 'superadmin@taskforge.app'],
            [
                'name'     => 'Super Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
                'role'     => 'superadmin',
            ]
        );

        // 2. ADMIN (Limited Access)
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@taskforge.app'],
            [
                'name'     => 'Admin Manager',
                'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
                'role'     => 'admin',
            ]
        );

        $this->command->info('Accounts ready:');
        $this->command->info('- superadmin@taskforge.app / 123456789 (Full Access)');
        $this->command->info('- admin@taskforge.app / 123456789 (Limited Access)');
    }
}
