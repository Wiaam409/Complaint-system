<?php

namespace App\Repositories;

use App\Models\Governorate;

class GovernorateRepository
{
    public function all()
    {
        return Governorate::get();
    }

    public function find($id)
    {
        return Governorate::find($id);
    }
}
