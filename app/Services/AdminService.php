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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminService
{
    // Transaction retry configuration
    protected $maxRetries = 3;
    protected $retryDelay = 100; // milliseconds

    /**
     * Generic transaction execution with retry logic
     */
    protected function executeTransaction(callable $transaction, callable $afterCommit = null)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                DB::beginTransaction();
                
                $result = $transaction();
                
                DB::commit();
                
                // Execute after-commit actions if provided
                if ($afterCommit && is_callable($afterCommit)) {
                    try {
                        $afterCommit($result);
                    } catch (\Exception $e) {
                        // Log after-commit failures but don't rollback the transaction
                        Log::error('After-commit action failed: ' . $e->getMessage());
                    }
                }
                
                return $result;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $lastException = $e;
                
                // Log the retry attempt
                if ($attempt < $this->maxRetries) {
                    Log::warning("Transaction attempt {$attempt} failed, retrying: " . $e->getMessage());
                    usleep($this->retryDelay * $attempt * 1000); // Exponential backoff
                }
            }
        }
        
        // If we get here, all retries failed
        throw $lastException;
    }

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
        return $this->executeTransaction(
            // Transaction logic
            function () use ($data) {
                $employee = User::create([
                    'name'           => $data['name'],
                    'email'          => $data['email'],
                    'phone'          => $data['phone'],
                    'password'       => Hash::make($data['password']),
                    'role'           => 'employee',
                    'governorate_id' => $data['governorate_id'],
                    'department_id'  => $data['department_id'],
                    'is_active'      => true,
                    'email_verified_at' => now(),
                ]);
                
                $employee->assignRole('employee');

                // Return result with after-commit data if needed
                return [
                    'response' => [
                        'success' => true,
                        'status'  => 201,
                        'message' => 'Employee created successfully',
                        'data'    => $this->formatEmployee($employee)
                    ],
                    'after_commit_data' => [
                        'employee_id' => $employee->id,
                        'employee_email' => $employee->email,
                        'created_at' => now()->toDateTimeString()
                    ]
                ];
            },
            // After-commit action (optional logging or notifications)
            function ($result) {
                // Log employee creation after successful transaction
                Log::info('Employee created successfully', [
                    'employee_id' => $result['after_commit_data']['employee_id'],
                    'email' => $result['after_commit_data']['employee_email'],
                    'created_at' => $result['after_commit_data']['created_at']
                ]);
                
                return $result['response']; // Return original response
            }
        );
    }

    public function updateEmployee(int $id, array $data): array
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($id, $data) {
                $employee = User::find($id);

                if (!$employee) {
                    return [
                        'response' => [
                            'success' => false,
                            'status'  => 404,
                            'message' => 'Employee not found',
                            'data'    => null
                        ]
                    ];
                }

                if ($employee->role !== 'employee') {
                    return [
                        'response' => [
                            'success' => false,
                            'status'  => 403,
                            'message' => 'Forbidden: only employee accounts can be updated',
                            'data'    => null
                        ]
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
                    'response' => [
                        'success' => true,
                        'status'  => 200,
                        'message' => 'Employee updated successfully',
                        'data'    => $this->formatEmployee($employee->fresh())
                    ],
                    'after_commit_data' => [
                        'employee_id' => $employee->id,
                        'updated_fields' => array_keys($update),
                        'updated_at' => now()->toDateTimeString()
                    ]
                ];
            },
            // After-commit action
            function ($result) {
                // Log the update after successful transaction
                if ($result['response']['success']) {
                    Log::info('Employee updated successfully', [
                        'employee_id' => $result['after_commit_data']['employee_id'],
                        'updated_fields' => $result['after_commit_data']['updated_fields'],
                        'updated_at' => $result['after_commit_data']['updated_at']
                    ]);
                }
                
                return $result['response']; // Return original response
            }
        );
    }

    public function toggleActive(int $id): array
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($id) {
                $employee = User::find($id);

                if (!$employee) {
                    return [
                        'response' => [
                            'success' => false,
                            'status'  => 404,
                            'message' => 'User not found',
                            'data'    => null
                        ]
                    ];
                }

                if ($employee->role === 'employee') {
                    $hasInProgressComplaints = $employee->handledComplaints()
                        ->where('status', 'in_progress')
                        ->exists();

                    if ($hasInProgressComplaints) {
                        return [
                            'response' => [
                                'success' => false,
                                'status'  => 409,
                                'message' => 'Cannot deactivate employee with complaints in progress',
                                'data'    => null
                            ]
                        ];
                    }
                }

                $oldStatus = $employee->is_active;
                $employee->is_active = ! (bool) $employee->is_active;
                $employee->save();

                return [
                    'response' => [
                        'success' => true,
                        'status'  => 200,
                        'message' => $employee->is_active ? 'Users activated' : 'Users deactivated',
                        'data'    => $this->formatEmployee($employee)
                    ],
                    'after_commit_data' => [
                        'employee_id' => $employee->id,
                        'old_status' => $oldStatus,
                        'new_status' => $employee->is_active,
                        'changed_at' => now()->toDateTimeString()
                    ]
                ];
            },
            // After-commit action
            function ($result) {
                // Log status change after successful transaction
                if ($result['response']['success']) {
                    Log::info('Employee status toggled', [
                        'employee_id' => $result['after_commit_data']['employee_id'],
                        'old_status' => $result['after_commit_data']['old_status'] ? 'active' : 'inactive',
                        'new_status' => $result['after_commit_data']['new_status'] ? 'active' : 'inactive',
                        'changed_at' => $result['after_commit_data']['changed_at']
                    ]);
                }
                
                return $result['response']; // Return original response
            }
        );
    }

    public function deleteEmployee(int $id, int $currentAdminId): array
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($id, $currentAdminId) {
                $employee = User::find($id);

                if (!$employee) {
                    return [
                        'response' => [
                            'success' => false,
                            'status'  => 404,
                            'message' => 'User not found',
                            'data'    => null
                        ]
                    ];
                }
                
                if ($employee->role === 'employee') {
                    $hasInProgressComplaints = $employee->handledComplaints()
                        ->where('status', 'in_progress')
                        ->exists();

                    if ($hasInProgressComplaints) {
                        return [
                            'response' => [
                                'success' => false,
                                'status'  => 409,
                                'message' => 'Cannot Delete employee with complaints in progress',
                                'data'    => null
                            ]
                        ];
                    }
                }

                // prevent admin from deleting themselves accidentally
                if ($employee->id === $currentAdminId) {
                    return [
                        'response' => [
                            'success' => false,
                            'status'  => 403,
                            'message' => 'You cannot delete your own account',
                            'data'    => null
                        ]
                    ];
                }

                // Store employee info before deletion for after-commit logging
                $employeeInfo = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'deleted_at' => now()->toDateTimeString()
                ];

                $employee->delete();

                return [
                    'response' => [
                        'success' => true,
                        'status'  => 200,
                        'message' => 'User deleted successfully',
                        'data'    => null
                    ],
                    'after_commit_data' => $employeeInfo
                ];
            },
            // After-commit action
            function ($result) {
                // Log the deletion after successful transaction
                if ($result['response']['success']) {
                    Log::info('Employee deleted', [
                        'employee_id' => $result['after_commit_data']['id'],
                        'name' => $result['after_commit_data']['name'],
                        'email' => $result['after_commit_data']['email'],
                        'role' => $result['after_commit_data']['role'],
                        'deleted_at' => $result['after_commit_data']['deleted_at']
                    ]);
                }
                
                return $result['response']; // Return original response
            }
        );
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