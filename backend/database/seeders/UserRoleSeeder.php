<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@dataforge.test')->first();

        if ($admin) {
            $admin->assignRole('admin');
        }

        // A demo field rep so the row-level scope is testable out of the box.
        $rep = User::updateOrCreate(
            ['email' => 'rep@dataforge.test'],
            ['name' => 'Kwame (Sales Rep)', 'password' => Hash::make('password')]
        );
        $rep->assignRole('sales_representative');

        // Hand the rep a couple of existing customers (no-op on an empty table).
        Customer::whereNull('assigned_representative_id')
            ->limit(2)
            ->update(['assigned_representative_id' => $rep->id]);
    }
}
