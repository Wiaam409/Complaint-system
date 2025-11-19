<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {        $this->call(GovernorateSeeder::class);

        // User::factory(10)->create();
        $this->call(DepartmentSeeder::class);

        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(EmployeeSeeder::class);
    }
}
