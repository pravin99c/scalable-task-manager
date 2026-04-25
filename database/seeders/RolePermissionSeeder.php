<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            'manage_users', 'manage_roles', 'manage_projects', 'manage_tasks', 'manage_settings', 'view_logs'
        ];

        foreach ($permissions as $p) {
            \App\Models\Permission::updateOrCreate(['slug' => $p], ['name' => ucwords(str_replace('_', ' ', $p))]);
        }

        // 2. Create Roles
        $superadmin = \App\Models\Role::updateOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin', 'description' => 'Full System Access']);
        $manager = \App\Models\Role::updateOrCreate(['slug' => 'manager'], ['name' => 'Project Manager', 'description' => 'Can manage projects and tasks']);
        $employee = \App\Models\Role::updateOrCreate(['slug' => 'employee'], ['name' => 'Employee', 'description' => 'Can view and update own tasks']);

        // 3. Assign All Permissions to Superadmin
        $superadmin->permissions()->sync(\App\Models\Permission::all());

        // 4. Assign Limited Permissions to Manager
        $manager->permissions()->sync(\App\Models\Permission::whereIn('slug', ['manage_projects', 'manage_tasks'])->get());

        // 5. Sync Existing Users to new Roles
        $saUser = \App\Models\User::where('role', 'superadmin')->first();
        if ($saUser) $saUser->roles()->sync([$superadmin->id]);

        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if ($adminUser) $adminUser->roles()->sync([$manager->id]);
    }
}
