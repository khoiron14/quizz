<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@cbt.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. Teacher User
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@cbt.test'],
            [
                'name' => 'Teacher User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $teacher->syncRoles(['teacher']);

        // 3. Student User
        $student = User::firstOrCreate(
            ['email' => 'student@cbt.test'],
            [
                'name' => 'Student User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $student->syncRoles(['student']);

        // Also ensure example.com aliases exist for convenience
        $testStudent = User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Student Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $testStudent->syncRoles(['student']);
    }
}
