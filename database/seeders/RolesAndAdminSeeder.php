<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    /**
     * Seed the Super Admin role and the initial administrator account.
     *
     * Idempotent: safe to run on every deploy. Existing accounts are NOT
     * modified (firstOrCreate), so a rotated password is never reset.
     *
     * The full permission matrix and starter roles (Stores Officer,
     * Storekeeper, Engineer, Viewer) are seeded in Phase 1 alongside the
     * Administration UI — see design spec §6.
     */
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'superadmin@ncatfmd.com.ng'],
            [
                'name' => 'NCAT FMD Administrator',
                // Change on first login — flagged below and documented in README.
                'password' => Hash::make('ChangeMe!NCAT2026'),
                'password_change_required' => true,
            ],
        );

        if (! $admin->hasRole($superAdmin)) {
            $admin->assignRole($superAdmin);
        }
    }
}
