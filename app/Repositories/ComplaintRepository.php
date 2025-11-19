<?php

namespace App\Repositories;

use App\Models\Complaint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ComplaintRepository
{
    protected $model;

    public function __construct(Complaint $model)
    {
        $this->model = $model;
    }


    public function create(array $data): Complaint
    {
        return $this->model->create($data);
    }


    public function findById(int $id): ?Complaint
    {
        return $this->model
            ->with(['attachments', 'logs', 'user'])
            ->find($id);
    }


    public function findByReference(string $reference): ?Complaint
    {
        return $this->model
            ->where('reference_number', $reference)
            ->with(['attachments', 'logs'])
            ->first();
    }


    public function listByUser(int $userId, int $limit = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }
    public function getComplaintsForEmployee($employee)
    {
        return \App\Models\Complaint::with(['department', 'governorate', 'lockedBy'])
            ->where('department_id', $employee->department_id)
            ->where('governorate_id', $employee->governorate_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
