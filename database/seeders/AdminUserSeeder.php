<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Administrator bootstrap is restricted to APP_ENV=local.');
        }

        $email = env('TASKFORGE_BOOTSTRAP_ADMIN_EMAIL');
        $password = env('TASKFORGE_BOOTSTRAP_ADMIN_PASSWORD');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || strlen($password) < 16) {
            throw new RuntimeException('Set a valid TASKFORGE_BOOTSTRAP_ADMIN_EMAIL and a unique TASKFORGE_BOOTSTRAP_ADMIN_PASSWORD of at least 16 characters.');
        }

        $role = Role::query()->where('slug', 'superadmin')->first();
        if (! $role) {
            throw new RuntimeException('Run RolePermissionSeeder before AdminUserSeeder.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Local Administrator',
                'password' => Hash::make($password),
                'role' => 'superadmin',
            ],
        );

        // The legacy users.role column remains for compatibility, but the
        // role_user pivot is the source of authorization decisions.
        $user->forceFill(['role' => 'superadmin'])->save();
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        $this->command?->info('Local administrator configured (credentials not displayed).');
    }
}
