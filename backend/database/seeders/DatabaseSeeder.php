<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate (no factory) so seeding is idempotent and works in the
        // --no-dev production image, which strips Faker.
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@dataforge.test'],
            [
                'name' => 'Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserRoleSeeder::class,
            ProspectSeeder::class,
            VisitSeeder::class,
        ]);
    }
}
