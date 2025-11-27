<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DepartmentGovernorate extends Pivot
{
    protected $fillable = ['department_id', 'governorate_id'];
    public function governorates()
    {
        return $this->belongsToMany(Governorate::class, 'department_governorates');
    }
}
