<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Complaint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class JMeterTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use transaction for faster bulk inserts
        DB::beginTransaction();
        
        try {
            // Pre-calculate common values
            $now = now();
            $citizenCount = 100;
            $employeeCount = 20;
            $complaintCount = 200;
            
            // ========== CREATE CITIZENS (Optimized) ==========
            
            for ($i = 0; $i < $citizenCount; $i++) {
                $currentId = $i + 1;
                $citizen = User::create([
                    'name' => "JMeter Citizen $currentId",
                    'email' => "citizen$currentId@jmeter.test",
                    'phone' => '09' . random_int(10000000, 99999999), // Faster than mt_rand
                    'password' => Hash::make('jmeter123'),
                    'role' => 'citizen',
                    'governorate_id' => 1,
                    'fcm_token' => 'jmeter_fcm_' . $currentId,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $citizen->assignRole('citizen');
            }
            
            // Get inserted citizen IDs (all at once, more efficient)
            $citizenIds = DB::table('users')
                ->where('email', 'like', 'citizen%@jmeter.test')
                ->orderBy('id', 'desc')
                ->limit($citizenCount)
                ->pluck('id')
                ->toArray();
            
            echo "Created $citizenCount test citizens\n";
            
            // ========== CREATE EMPLOYEES (Optimized) ==========
            $employees = [];
            
            for ($i = 0; $i < $employeeCount; $i++) {
                $currentId = $i + 1;
                $employee = User::create([
                    'name' => "JMeter Employee $currentId",
                    'email' => "employee$currentId@jmeter.test",
                    'phone' => '09' . random_int(10000000, 99999999),
                    'password' => Hash::make('jmeter123'),
                    'role' => 'employee',
                    'governorate_id' => 1,
                    'department_id' => 1,
                    'is_active' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $employee->assignRole('employee');
            }
            
            echo "Created $employeeCount test employees\n";
            
            // ========== CREATE ADMIN (Single) ==========
            $admin = User::create([
                'name' => "JMeter Admin",
                'email' => "admin@jmeter.test",
                'phone' => '09' . random_int(10000000, 99999999),
                'password' => Hash::make('jmeter123'),
                'role' => 'admin',
                'governorate_id' => 1,
                'department_id' => 1,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $admin->assignRole('admin');
            
            echo "Created admin@jmeter.test\n";
            
            // ========== CREATE COMPLAINTS (Most Optimized) ==========
            $complaints = [];
            $baseTimestamp = time(); // For unique reference numbers
            $citizenCount = count($citizenIds);
            
            // Pre-generate all random numbers to avoid function calls in loop
            $governorateIds = [];
            $departmentIds = [];
            
            for ($i = 0; $i < $complaintCount; $i++) {
                $governorateIds[] = random_int(1, 5);
                $departmentIds[] = random_int(1, 10);
            }
            
            // Create complaints in bulk (avoid array_rand in loop)
            for ($i = 0; $i < $complaintCount; $i++) {
                $complaintNumber = $i + 1;
                $complaints[] = [
                    'title' => "JMeter Test Complaint $complaintNumber",
                    'description' => "This is a test complaint created for JMeter load testing. Description details go here.",
                    'user_id' => $citizenIds[$i % $citizenCount], // Round-robin distribution
                    'governorate_id' => $governorateIds[$i],
                    'department_id' => $departmentIds[$i],
                    'reference_number' => 'COM-' . $baseTimestamp . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    'location' => "Test Location $complaintNumber",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            
            // Bulk insert all complaints at once
            DB::table('complaints')->insert($complaints);
            echo "Created $complaintCount test complaints\n";
            
            DB::commit();
            
            echo "JMeter test data seeding completed!\n";
            echo "Citizen: citizen201@jmeter.test / jmeter123\n";
            echo "Employee: employee1@jmeter.test / jmeter123\n";
            echo "Admin: admin@jmeter.test / jmeter123\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}