<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $admin->syncPermissions(Permission::all());

        // Field sales rep: works customers + their own visits/tasks, reads reports.
        // Row-level scoping (own/covered customers only) is enforced by the policy
        // + Customer::scopeVisibleTo, not by these module permissions.
        $rep = Role::firstOrCreate([
            'name' => 'sales_representative',
            'guard_name' => 'web',
        ]);

        $rep->syncPermissions([
            'customers.view', 'customers.update',
            'prospects.view', 'prospects.create', 'prospects.update', 'prospects.delete',
            'visits.view', 'visits.create', 'visits.update', 'visits.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'reports.view',
        ]);
    }
}
