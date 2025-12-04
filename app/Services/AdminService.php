<?php

namespace App\Services;

use App\Models\Complaint;
use Illuminate\Support\Facades\Auth;

class AdminService
{
    public function getComplaintsByFilters($departmentId, $governorateId)
    {
        $query = Complaint::query()
            ->where('department_id', $departmentId)
            ->where('governorate_id', $governorateId)
            ->with(['attachments', 'user:id,name']);

        $complaints = $query->paginate(10);
        $formatted = $complaints->map(function ($c) {
            return [
                'id' => $c->id,
                'reference_number' => $c->reference_number,
                'title' => $c->title,
                'description' => $c->description,
                'location' => $c->location,
                'status' => $c->status,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,

                'user' => [
                    'id'   => $c->user->id ?? null,
                    'name' => $c->user->name ?? null,
                ],

                'attachments' => $c->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_path' => $a->file_path,
                    ];
                }),
            ];
        });

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Complaints retrieved successfully',
            'data'    => $formatted
        ];
    }
}
