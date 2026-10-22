<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all CBT permissions
        $permissions = [
            'exams.view',
            'exams.create',
            'exams.edit',
            'exams.delete',
            'exams.monitor',
            'exams.extend_time',
            'exams.reset_session',
            'exams.grade',
            'exams.take',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Student Role & Permissions
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $studentRole->syncPermissions([
            'exams.view',
            'exams.take',
        ]);

        // Teacher Role & Permissions
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherRole->syncPermissions([
            'exams.view',
            'exams.create',
            'exams.edit',
            'exams.delete',
            'exams.monitor',
            'exams.extend_time',
            'exams.reset_session',
            'exams.grade',
        ]);

        // Admin Role (all permissions)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);
    }
}
