<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintLog;
use App\Models\system_logs;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
    public function createEmployee(array $data): array
    {
        $employee = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'password'       => Hash::make($data['password']),
            'role'           => 'employee',
            'governorate_id' => $data['governorate_id'] ,
            'department_id'  => $data['department_id'] ,
            'is_active'      =>  true,

            'email_verified_at' => now(),
        ]);
        $employee->assignRole('employee');

        return [
            'success' => true,
            'status'  => 201,
            'message' => 'Employee created successfully',
            'data'    => $this->formatEmployee($employee)
        ];
    }



    public function updateEmployee(int $id, array $data): array
    {
        $employee = User::find($id);

        if (!$employee) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'Employee not found',
                'data'    => null
            ];
        }

        if ($employee->role !== 'employee') {
            return [
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden: only employee accounts can be updated',
                'data'    => null
            ];
        }

        $allowed = ['name', 'email', 'phone', 'password', 'governorate_id', 'department_id', 'is_active'];

        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'password' && $data['password']) {
                    $update['password'] = Hash::make($data['password']);
                } elseif ($field !== 'password') {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (!empty($update)) {
            $employee->update($update);
        }

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Employee updated successfully',
            'data'    => $this->formatEmployee($employee->fresh())
        ];
    }


    public function toggleActive(int $id): array
    {
        $employee = User::find($id);

        if (!$employee) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'User not found',
                'data'    => null
            ];
        }

        if ($employee->role === 'employee') {
            $hasInProgressComplaints = $employee->handledComplaints()
                ->where('status', 'in_progress')
                ->exists();

            if ($hasInProgressComplaints) {
                return [
                    'success' => false,
                    'status'  => 409,
                    'message' => 'Cannot deactivate employee with complaints in progress',
                    'data'    => null
                ];
            }
        }

        $employee->is_active = ! (bool) $employee->is_active;
        $employee->save();

        return [
            'success' => true,
            'status'  => 200,
            'message' => $employee->is_active ? 'Users activated' : 'Users deactivated',
            'data'    => $this->formatEmployee($employee)
        ];
    }



    public function deleteEmployee(int $id, int $currentAdminId): array
    {
        $employee = User::find($id);

        if (!$employee) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'User not found',
                'data'    => null
            ];
        }
        if ($employee->role === 'employee') {
            $hasInProgressComplaints = $employee->handledComplaints()
                ->where('status', 'in_progress')
                ->exists();

            if ($hasInProgressComplaints) {
                return [
                    'success' => false,
                    'status'  => 409,
                    'message' => 'Cannot Delete employee with complaints in progress',
                    'data'    => null
                ];
            }
        }

        // prevent admin from deleting themselves accidentally
        if ($employee->id === $currentAdminId) {
            return [
                'success' => false,
                'status'  => 403,
                'message' => 'You cannot delete your own account',
                'data'    => null
            ];
        }


        $employee->delete();

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'User deleted successfully',
            'data'    => null
        ];
    }


    public function getEmployeesByDepartmentGovernorate(int $governorateId, int $departmentId): array
    {
        $employees = User::where('role', 'employee')
            ->where('governorate_id', $governorateId)
            ->where('department_id', $departmentId)
            ->select(['id', 'name', 'email', 'phone', 'is_active'])
            ->get();

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Employees retrieved successfully',
            'data'    => $employees
        ];
    }


    public function getEmployeeWithComplaints(int $id): array
    {
        $employee = User::find($id);

        if (!$employee || $employee->role !== 'employee') {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'Employee not found',
                'data'    => null
            ];
        }

        $complaintIds = ComplaintLog::where('user_id', $id)
            ->where('action', 'status_changed')
            ->pluck('complaint_id')
            ->unique()
            ->toArray();

        $complaints = Complaint::with(['attachments:id,complaint_id,file_path'])
            ->whereIn('id', $complaintIds)
            ->get();

        $payload = $this->formatEmployee($employee);

        $payload['complaints'] = $complaints->map(function ($c) {
            return [
                'id' => $c->id,
                'reference_number' => $c->reference_number,
                'title' => $c->title,
                'description' => $c->description,
                'location' => $c->location,
                'status' => $c->status,
                'created_at' => $c->created_at,
                'attachments' => $c->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_path' => $a->file_path
                    ];
                })
            ];
        });

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Employee retrieved successfully',
            'data'    => $payload
        ];
    }


    protected function formatEmployee($e)
    {
        if (!$e) return null;

        return [
            'id'            => $e->id,
            'name'          => $e->name,
            'email'         => $e->email,
            'phone'         => $e->phone,
            'governorate_id' => $e->governorate_id,
            'department_id' => $e->department_id,
            'created_at'    => $e->created_at,
            'updated_at'    => $e->updated_at,
        ];
    }
    public function complaintsSummary(string $from, string $to, ?int $governorateId = null, ?int $departmentId = null): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end   = Carbon::parse($to)->endOfDay();


        $query = Complaint::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($governorateId) {
            $query->where('governorate_id', $governorateId);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $total = (clone $query)->count();

        $byStatusRaw = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $allStatuses = [
            'new',
            'in_progress',
            'resolved',
            'rejected',
            'needs_update'
        ];

        $byStatus = [];
        foreach ($allStatuses as $s) {
            $byStatus[$s] = isset($byStatusRaw[$s]) ? (int)$byStatusRaw[$s] : 0;
        }

        $breakdown = [];
        foreach ($byStatus as $status => $count) {
            $breakdown[] = ['status' => $status, 'count' => $count];
        }

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Complaints statistics retrieved successfully',
            'data'    => [
                'filters' => [
                    'from' => $start->toDateTimeString(),
                    'to' => $end->toDateTimeString(),
                    'governorate_id' => $governorateId,
                    'department_id' => $departmentId,
                ],
                'total' => (int) $total,
                'by_status' => $byStatus,
                'breakdown' => $breakdown,
            ],
        ];
    }

    public function getStats(array $filters): array
    {
        $query = SystemLog::query();

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // إجمالي الطلبات
        $totalRequests = $query->count();

        // إجمالي الأخطاء
        $totalErrors = (clone $query)->where('is_error', true)->count();

        // متوسط زمن التنفيذ
        $avgTime = (clone $query)->avg('execution_time_ms');

        // أسرع طلب
        $minTime = (clone $query)->min('execution_time_ms');

        // أبطأ طلب
        $maxTime = (clone $query)->max('execution_time_ms');

        // أهم الإندبوينتات (مع نفس الفلاتر)
        $topEndpoints = (clone $query)
            ->selectRaw('endpoint, COUNT(*) as count')
            ->groupBy('endpoint')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'System stats retrieved successfully',

            'data' => [
                'total_requests'   => $totalRequests,
                'total_errors'     => $totalErrors,
                'avg_execution_ms' => round($avgTime, 2),
                'min_execution_ms' => $minTime,
                'max_execution_ms' => $maxTime,
                'top_endpoints'    => $topEndpoints,
            ]
        ];
    }
}
