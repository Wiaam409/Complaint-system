<?php

namespace App\Repositories;

use App\Models\Department;

class DepartmentRepository
{
    public function all()
    {
        return Department::with('governorates')->get();
    }

    public function find($id)
    {
        return Department::with('governorates')->find($id);
    }

    public function groupedByGovernorate()
    {
        return \App\Models\Governorate::with(['departments:id,name'])
            ->select('id', 'name')
            ->get();
    }

    public function getByGovernorate($governorateId)
    {
        return Department::whereHas('governorates', function ($q) use ($governorateId) {
            $q->where('governorate_id', $governorateId);
        })->select('id', 'name')->get();
    }
}
