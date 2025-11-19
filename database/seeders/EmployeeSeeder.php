<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Governorate;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    public function run()
    {
        $governorates = Governorate::all();
        $departments = Department::all();

        foreach ($governorates as $governorate) {

            foreach ($departments as $department) {

                // نضمن الربط بين القسم والمحافظة
                $department->governorates()->syncWithoutDetaching([$governorate->id]);

                for ($i = 1; $i <= 2; $i++) {

                    // اسم الموظف
                    $name = "{$department->name} Employee {$i} - {$governorate->name}";

                    // تعديل الإيميل ليكون صالح
                    $emailName = strtolower(
                        str_replace([' ', '-'], '_', $department->name . "_employee_{$i}_" . $governorate->name)
                    );

                    $email = preg_replace('/[^a-z0-9_]/', '', $emailName) . '@example.com';

                    // إنشاء المستخدم
                    $user = User::create([
                        'name'           => $name,
                        'email'          => $email,
                        'phone'          => '09' . rand(10000000, 99999999),
                        'password'       => Hash::make('password'),
                        'role'           => 'employee',
                        'department_id'  => $department->id,
                        'governorate_id' => $governorate->id,
                        'email_verified_at' => now(),

                    ]);
                }
            }
        }
    }
}
