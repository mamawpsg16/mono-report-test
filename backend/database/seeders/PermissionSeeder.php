<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            'customers',
            'visits',
            'tasks',
            'reports',
        ];

        $actions = [
            'view',
            'create',
            'update',
            'delete',
        ];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        Permission::firstOrCreate(['name' => 'roles.manage', 'guard_name' => 'web']);
    }
}
