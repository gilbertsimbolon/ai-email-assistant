<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Exactly two roles per claude.txt — Admin and Agent, no more. Admin's
 * "manage everything" permission also acts as a blanket bypass via
 * Gate::before (see AppServiceProvider::boot()), so Admin does not need
 * every Agent permission duplicated onto it.
 */
class RolePermissionSeeder extends Seeder
{
    protected const ADMIN_PERMISSIONS = [
        'manage ai center',
        'manage gmail',
        'manage settings',
        'manage users',
        'manage reports',
        'manage models',
        'manage prompt',
        'manage workflow',
        'manage everything',
    ];

    protected const AGENT_PERMISSIONS = [
        'inbox',
        'generate ai',
        'regenerate ai',
        'translate email',
        'summarize thread',
        'approve draft',
        'edit draft',
        'send email',
        'view reports',
    ];

    public function run(): void
    {
        foreach ([...self::ADMIN_PERMISSIONS, ...self::AGENT_PERMISSIONS] as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::ADMIN_PERMISSIONS);

        $agent = Role::query()->firstOrCreate(['name' => 'Agent', 'guard_name' => 'web']);
        $agent->syncPermissions(self::AGENT_PERMISSIONS);
    }
}
