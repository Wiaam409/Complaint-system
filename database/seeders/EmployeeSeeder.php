<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Governorate;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    public function run()
    {
        $governorates = Governorate::all();
        $departments = Department::all();
        $now = now();

        // Bulk insert department-governorate relationships
        $pivotData = [];
        foreach ($departments as $department) {
            foreach ($governorates as $governorate) {
                $pivotData[] = [
                    'department_id' => $department->id,
                    'governorate_id' => $governorate->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('department_governorates')->insert($pivotData);
        $passwordHash = Hash::make('password'); // Hash once, use for all

        foreach ($governorates as $governorate) {
            foreach ($departments as $department) {
                for ($i = 1; $i <= 2; $i++) {
                    $email = strtolower(str_replace([' ', '-'], '_', $department->name . "_employee_{$i}_" . $governorate->name . "@example.com"));
                    $user = User::create([
                        'name' => "{$department->name} Employee {$i} - {$governorate->name}",
                        'email' => $email,
                        'phone' => '09' . mt_rand(10000000, 99999999),
                        'password' => $passwordHash,
                        'role' => 'employee',
                        'department_id' => $department->id,
                        'governorate_id' => $governorate->id,
                        'email_verified_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $user->assignRole('employee');
                }
            }
        }
    }
}
