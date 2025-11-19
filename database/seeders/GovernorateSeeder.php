<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Governorate;

class GovernorateSeeder extends Seeder
{
    public function run()
    {
        $governorates = [
            'Damascus',
            'Rif Dimashq',
            'Aleppo',
            'Homs',
            'Hama',
            'Latakia',
            'Tartous',
            'Idlib',
            'Deir ez-Zor',
            'Hasakah',
            'Raqqa',
            'Daraa',
            'As-Suwayda',
            'Quneitra',
        ];

        foreach ($governorates as $g) {
            Governorate::firstOrCreate(['name' => $g]);
        }
    }
}
