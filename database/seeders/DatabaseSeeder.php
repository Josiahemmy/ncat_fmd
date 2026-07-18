<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // RBAC first so roles/permissions exist before the admin user.
            PermissionSeeder::class,
            RoleSeeder::class,
            // Reference data.
            StoreSeeder::class,
            AircraftTypeSeeder::class,
            AircraftSeeder::class,
            AtaChapterSeeder::class,
            DocumentCounterSeeder::class,
            // The initial Super Admin account.
            RolesAndAdminSeeder::class,
        ]);
    }
}
