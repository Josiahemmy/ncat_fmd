<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Create the starter roles and sync their default permissions.
     *
     * Idempotent, but only ADDS the default permissions — it does not strip
     * permissions an admin has since added via the UI (uses givePermissionTo,
     * not syncPermissions) so re-seeding never undoes department customisation.
     * Super Admin is created here for completeness; its access comes from the
     * Gate::before bypass and it is immutable in the UI.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Super Admin — holds every permission (in addition to the bypass).
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->givePermissionTo(Permission::where('guard_name', 'web')->get());

        foreach (Config::get('permissions.roles', []) as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            foreach ($permissions as $permission) {
                if (Permission::where(['name' => $permission, 'guard_name' => 'web'])->exists()) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
