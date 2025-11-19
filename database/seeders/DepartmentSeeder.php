<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Governorate;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        $departments = [
            'Electricity Department',
            'Water Department',
            'Municipality',
            'Traffic Department',
            'Police Department',
            'Emergency Medical Services',
            'Telecommunication Department',
            'Health Department',
            'Education Department',
            'Transportation Department'
        ];

        $governorates = Governorate::all()->pluck('id')->toArray();

        foreach ($departments as $name) {

            $department = Department::firstOrCreate(['name' => $name]);

            $department->governorates()->sync($governorates);
        }
    }
}
