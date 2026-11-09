<?php

namespace Database\Seeders;

use App\Auth\ClinicRole;
use App\Auth\Permission as PermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy to sync new permissions.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (ClinicRole::cases() as $clinicRole) {
            // clinic_id = null: global template usable in every clinic.
            $role = Role::query()->firstOrCreate(
                ['name' => $clinicRole->value, 'guard_name' => 'web', 'clinic_id' => null],
            );

            $role->syncPermissions(array_map(fn (PermissionEnum $p) => $p->value, $clinicRole->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
