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
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@taskforge.app'],
            [
                'name'     => 'Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('123456789'),
                'role'     => 'admin',
            ]
        );

        $this->command->info('Admin user ready → admin@taskforge.app / 123456789');
    }
}
