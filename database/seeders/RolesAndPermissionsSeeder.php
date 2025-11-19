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
        // Clear cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $this->createPermissions();

        // Create roles and assign permissions
        $this->createRoles();

        // Create sample users
        $this->createSampleUsers();
    }

    private function createPermissions(): void
    {
        // Complaint permissions
        $permissions = [
            // Complaint management
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

            // User management (admin only)
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage_roles',

            // System management (admin only)
            'system.dashboard.view',
            'system.reports.generate',
            'system.reports.export',
            'system.audit_logs.view',
            'system.backup.manage',
            'system.settings.manage',

            // Department management (admin only)
            'departments.view',
            'departments.manage',
            'departments.staff.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api' // Default to api guard
            ]);
        }

        $this->command->info('All permissions created successfully.');
    }

    private function createRoles(): void
    {
        // 🔹 Citizen Role (Mobile App Users)
        $citizen = Role::firstOrCreate([
            'name' => 'citizen',
        ]);
        $citizen->syncPermissions([
            'complaints.create',
            'complaints.view_own',
            'complaints.attach_files',
            'complaints.view_history',
        ]);

        // 🔹 Employee Role (Department Staff - React Dashboard)
        $employee = Role::firstOrCreate([
            'name' => 'employee',
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

        // 🔹 Admin Role (Full Access - React Dashboard)
        $admin = Role::firstOrCreate([
            'name' => 'admin',
        ]);
        $admin->syncPermissions(Permission::all());

        $this->command->info('Roles created and permissions assigned successfully.');
    }

    private function createSampleUsers(): void
    {
        // Create sample department
        $electricityDept = Department::firstOrCreate(
            ['name' => 'Electricity Ministry'],
            [
                'code' => 'MOE',
            ]
        );

        // 🔹 Citizen User (Mobile App)
        $citizenUser = User::firstOrCreate(
            ['email' => 'citizen@example.com'],
            [
                'name' => 'Ahmed Mohamed',
                'phone' => '0912345678',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $citizenUser->assignRole('citizen');

        // 🔹 Employee User (React Dashboard)
        $employeeUser = User::firstOrCreate(
            ['email' => 'employee@electricity.com'],
            [
                'name' => 'Mahmoud Abdullah',
                'phone' => '0912345679',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'department_id' => $electricityDept->id,
            ]
        );
        $employeeUser->assignRole('employee');

        // 🔹 Admin User (React Dashboard)
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@complaints.gov'],
            [
                'name' => 'System Administrator',
                'phone' => '0912345681',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('admin');

        $this->command->info('Sample users created successfully.');
        $this->command->info('🔐 Login Credentials:');
        $this->command->info('Citizen: citizen@example.com / password');
        $this->command->info('Employee: employee@electricity.com / password');
        $this->command->info('Admin: admin@complaints.gov / password');
    }
}
