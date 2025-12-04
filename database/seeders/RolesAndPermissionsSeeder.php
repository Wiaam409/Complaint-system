<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached roles/permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
        $this->createSampleUsers();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Complaint
            'complaints.view',
            'complaints.create',
            'complaints.update',
            'complaints.delete',
            'complaints.view_any',
            'complaints.view_own',
            'complaints.update_status',
            'complaints.add_notes',
            'complaints.attach_files',
            'complaints.view_history',
            'complaints.export',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage_roles',

            // System
            'system.dashboard.view',
            'system.reports.generate',
            'system.reports.export',
            'system.audit_logs.view',
            'system.backup.manage',
            'system.settings.manage',

            // Departments
            'departments.view',
            'departments.manage',
            'departments.staff.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $this->command->info('All permissions created.');
    }

    private function createRoles(): void
    {
        // Citizen
        $citizen = Role::firstOrCreate([
            'name' => 'citizen',
            'guard_name' => 'api'
        ]);
        $citizen->syncPermissions([
            'complaints.create',
            'complaints.view_own',
            'complaints.attach_files',
            'complaints.view_history',
        ]);

        // Employee
        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'api'
        ]);
        $employee->syncPermissions([
            'complaints.view',
            'complaints.update',
            'complaints.update_status',
            'complaints.add_notes',
            'complaints.attach_files',
            'complaints.view_history',
            'system.dashboard.view',
        ]);

        // Admin
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);
        $admin->syncPermissions(Permission::all());

        $this->command->info('Roles created and assigned.');
    }

    private function createSampleUsers(): void
    {
        $department = Department::first();

        // Citizen User
        $citizenUser = User::firstOrCreate(
            ['email' => 'citizen@example.com'],
            [
                'name' => 'Ahmed Mohamed',
                'phone' => '0912345678',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'citizen',
            ]
        );
        $citizenUser->assignRole('citizen');

        // Employee User
        $employeeUser = User::firstOrCreate(
            ['email' => 'employee@electricity.com'],
            [
                'name' => 'Mahmoud Abdullah',
                'phone' => '0912345679',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'department_id' => $department?->id,
                'role' => 'employee',
            ]
        );
        $employeeUser->assignRole('employee');

        // Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@complaints.gov'],
            [
                'name' => 'System Administrator',
                'phone' => '0912345681',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ]
        );
        $adminUser->assignRole('admin');

        $this->command->info('Sample users created.');
        $this->command->info('Credentials:');
        $this->command->info('Citizen: citizen@example.com / password');
        $this->command->info('Employee: employee@electricity.com / password');
        $this->command->info('Admin: admin@complaints.gov / password');
    }
}
