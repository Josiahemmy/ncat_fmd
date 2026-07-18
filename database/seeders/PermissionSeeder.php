<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** Create every permission in the catalogue (config/permissions.php). Idempotent. */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Config::get('permissions.groups', []) as $group) {
            foreach (array_keys($group['permissions']) as $name) {
                Permission::findOrCreate($name, 'web');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
