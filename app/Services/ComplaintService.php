<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Notification;
use App\Repositories\ComplaintRepository;
use App\Repositories\GovernorateRepository;
use App\Repositories\DepartmentRepository;

use App\Models\ComplaintAttachment;
use App\Models\ComplaintLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ComplaintService
{
    protected $repo;
    protected $governorates;
    protected $departments;
    protected $firebaseService;

    protected $cacheTTL = 300;

    // Transaction retry configuration
    protected $maxRetries = 3;
    protected $retryDelay = 100; // milliseconds

    public function __construct(
        ComplaintRepository $repo,
        GovernorateRepository $governorates,
        DepartmentRepository $departments,
        FirebaseService $firebaseService
    ) {
        $this->repo = $repo;
        $this->governorates = $governorates;
        $this->departments = $departments;
        $this->firebaseService = $firebaseService;
    }

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

    /**
     * Send notifications after transaction commit
     */
    protected function sendAfterCommitNotifications(array $notificationData): void
    {
        try {
            // Send Firebase notification
            if (isset($notificationData['firebase'])) {
                $this->firebaseService->sendToToken(
                    $notificationData['firebase']['token'],
                    $notificationData['firebase']['title'],
                    $notificationData['firebase']['message'],
                    $notificationData['firebase']['data']
                );
            }

            // Create database notification
            if (isset($notificationData['database'])) {
                Notification::create($notificationData['database']);
            }

        } catch (\Exception $e) {
            Log::error('Notification sending failed: ' . $e->getMessage());
        }
    }

    public function create(array $data, $files = [])
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($data, $files) {
                $user = Auth::user();

            // التأكد أن المحافظة موجودة
            if (!$this->governorates->find($data['governorate_id'] ?? null)) {
                abort(422, 'Invalid governorate_id');
            }

            // التأكد أن القسم موجود
            if (!$this->departments->find($data['department_id'] ?? null)) {
                abort(422, 'Invalid department_id');
            }

            $data['reference_number'] = $this->generateReference();
            $data['user_id'] = $user->id;
            $complaint = $this->repo->create($data);
            $sent = $this->firebaseService->sendToToken(
                $complaint->user->fcm_token,
                'New Complaint Submitted',
                'Your complaint has been received',
                [
                    'reference_number' => $data['reference_number'],
                    'submitted_at' => now()->toDateTimeString(),
                ]
            );
            Notification::create([
                'user_id' => $user->id,
                'title' => 'New Complaint Submitted',
                'message' => 'Your complaint has been received',
                'type' => 'new_complaint',
                'complaint_id' => $complaint->id,
                'metadata' => [
                    'reference_number' => $data['reference_number'],
                    'submitted_at' => now()->toDateTimeString(),
                ],
            ]);
            if (!empty($files)) {
                $this->saveAttachments($complaint, $files);
            }

            $this->createLog($complaint->id, $user->id, 'created', 'Complaint created');
            $this->clearUserComplaintsCache($user->id);

            return $this->repo->findById($complaint->id);
        });
    }

    private function generateReference()
    {
        return 'COM-' . strtoupper(Str::random(8));
    }

    private function saveAttachments($complaint, $files)
    {
        foreach ($files as $file) {

            $extension = $file->getClientOriginalExtension();
            $fileName = uniqid() . '.' . $extension;

            $folderPath = public_path("complaints/{$complaint->id}");

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $file->move($folderPath, $fileName);

            $fileUrl = url("complaints/{$complaint->id}/{$fileName}");

            ComplaintAttachment::create([
                'complaint_id'  => $complaint->id,
                'file_path'     => $fileUrl,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
            ]);
        }
    }


    private function createLog($complaintId, $userId, $action, $notes = null)
    {
        ComplaintLog::create([
            'complaint_id' => $complaintId,
            'user_id'      => $userId,
            'action'       => $action,
            'notes'        => $notes,
        ]);
    }

    private function clearUserComplaintsCache($userId)
    {
        Cache::forget("user_{$userId}_complaints");
    }

 public function getUserComplaints(int $limit = 15)
{
    $userId = Auth::id();
    $page   = request('page', 1);

    $cacheKey = "user_{$userId}_complaints_page_{$page}_limit_{$limit}";

    return Cache::remember(
        $cacheKey,
        $this->cacheTTL,
        fn() => $this->repo->listByUser($userId, $limit)
    );
}

    public function getComplaintById($id)
    {
        return $this->repo->findById($id);
    }

    public function getGovernorates()
    {
        return $this->governorates->all();
    }

    public function listDepartmentsGrouped()
    {
        return $this->departments->groupedByGovernorate();
    }

    public function getDepartmentsByGovernorate($governorateId)
    {
        if (!$this->governorates->find($governorateId)) {
            abort(404, 'Governorate not found');
        }

        return $this->departments->getByGovernorate($governorateId);
    }

    public function updateStatus($complaintId, $employeeId, $status)
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($complaintId, $employeeId, $status) {
                $complaint = $this->repo->findById($complaintId);
                $employee = Auth::user();

                if (!$complaint) {
                    return [
                        'success' => false,
                        'status' => 404,
                        'message' => 'Complaint not found',
                        'data' => null
                    ];
                }

                // EMPLOYEE ACCESS CHECK
                if ($employee->department_id != $complaint->department_id || $employee->governorate_id != $complaint->governorate_id) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => 'You are not authorized to process this complaint',
                        'data' => null
                    ];
                }

                $allowed = ['new', 'in_progress', 'resolved', 'rejected'];
                if (!in_array($status, $allowed)) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'message' => 'Invalid status value',
                        'data' => null
                    ];
                }

                // LOCK if in_progress
                if ($status === 'in_progress') {
                    if ($complaint->is_locked && $complaint->locked_by != $employeeId) {
                        return [
                            'success' => false,
                            'status' => 403,
                            'message' => 'Complaint is currently locked by another employee',
                            'data' => null
                        ];
                    }

                    if (!$complaint->is_locked) {
                        $complaint->update([
                            'is_locked' => true,
                            'locked_at' => now(),
                            'locked_by' => $employeeId
                        ]);

                        ComplaintLog::create([
                            'complaint_id' => $complaintId,
                            'user_id' => $employeeId,
                            'action' => 'locked',
                            'notes' => 'Complaint locked automatically'
                        ]);
                    }
                }

                // UNLOCK if resolved/rejected
                if (in_array($status, ['resolved', 'rejected'])) {
                    if ($complaint->locked_by != $employeeId) {
                        return [
                            'success' => false,
                            'status' => 403,
                            'message' => 'Cannot complete, complaint locked by another employee',
                            'data' => null
                        ];
                    }

                    $complaint->update([
                        'is_locked' => false,
                        'locked_at' => null,
                        'locked_by' => null
                    ]);

                    ComplaintLog::create([
                        'complaint_id' => $complaintId,
                        'user_id' => $employeeId,
                        'action' => 'unlocked',
                        'notes' => 'Complaint unlocked automatically after completion'
                    ]);
                }

                $old_status = $complaint->status;

                // UPDATE STATUS
                $complaint->update(['status' => $status]);

                ComplaintLog::create([
                    'complaint_id' => $complaintId,
                    'user_id' => $employeeId,
                    'action' => 'status_changed',
                    'notes' => "Status changed to: {$status}"
                ]);

                // Return result with notification data for after-commit
                return [
                    'response' => [
                        'success' => true,
                        'status' => 200,
                        'message' => 'Status updated successfully',
                        'data' => $complaint
                    ],
                    'notification_data' => [
                        'firebase' => [
                            'token' => $complaint->user->fcm_token,
                            'title' => 'Status Update',
                            'message' => 'Your complaint status has been updated to ' . $status,
                            'data' => [
                                'old_status' => $old_status,
                                'new_status' => $status,
                                'updated_at' => now()->toDateTimeString(),
                            ],
                        ],
                        'database' => [
                            'user_id' => $complaint->user->id,
                            'title' => 'Status Updated',
                            'message' => 'Your complaint status has been updated to ' . $status,
                            'type' => 'status_update',
                            'complaint_id' => $complaint->id,
                            'metadata' => [
                                'old_status' => $old_status,
                                'new_status' => $status,
                                'updated_at' => now()->toDateTimeString(),
                            ],
                        ]
                    ]
                ];
            },
            // After-commit action
            function ($result) {
                $this->sendAfterCommitNotifications($result['notification_data']);
                return $result['response']; // Return original response
            }
        );
    }

    public function getEmployeeComplaints()
    {
        $employee = Auth::user();

        if ($employee->role !== 'employee') {
            return [
                'success' => false,
                'status' => 403,
                'message' => 'Unauthorized: only employees can access this',
                'data' => null
            ];
        }

        $complaints = \App\Models\Complaint::where('department_id', $employee->department_id)
            ->where('governorate_id', $employee->governorate_id)
            ->get();
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Employee complaints retrieved successfully',
            'data' => $complaints
        ];
    }

    public function requestUpdateFromEmployee($complaintId, $employeeId, $reason, $fieldsToUpdate = [])
    {
        return $this->executeTransaction(
            // Transaction logic
            function () use ($complaintId, $employeeId, $reason, $fieldsToUpdate) {
                $complaint = $this->repo->findById($complaintId);

                if (!$complaint) {
                    return [
                        'success' => false,
                        'status' => 404,
                        'message' => "Complaint not found",
                        'data' => null
                    ];
                }

                $employee = Auth::user();

                if ($employee->role !== 'employee') {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => "Only employees can request updates",
                        'data' => null
                    ];
                }

                if ($employee->department_id != $complaint->department_id) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => "You do not belong to this complaint's department",
                        'data' => null
                    ];
                }

                if ($employee->governorate_id != $complaint->governorate_id) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => "You do not have access to this governorate",
                        'data' => null
                    ];
                }

                if ($complaint->status !== 'in_progress') {
                    return [
                        'success' => false,
                        'status' => 409,
                        'message' => "Complaint must be in progress to request modification",
                        'data' => null
                    ];
                }

                if ($complaint->locked_by != $employeeId) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => "Complaint is locked by another employee",
                        'data' => null
                    ];
                }

                $complaint->update([
                    'status' => 'needs_update',
                    'is_locked' => false,
                    'locked_at' => null,
                    'locked_by' => null,
                    'meta' => json_encode([
                        'update_reason' => $reason,
                        'fields_to_update' => $fieldsToUpdate
                    ])
                ]);

                ComplaintLog::create([
                    'complaint_id' => $complaintId,
                    'user_id'      => $employeeId,
                    'action'       => 'request_update',
                    'notes'        => "Employee requested update: {$reason}"
                ]);

                // Return result with notification data for after-commit
                return [
                    'response' => [
                        'success' => true,
                        'status' => 200,
                        'message' => 'Update request sent successfully',
                        'data' => $complaint
                    ],
                    'notification_data' => [
                        'firebase' => [
                            'token' => $complaint->user->fcm_token,
                            'title' => 'Fields update required',
                            'message' => 'You have to update the required fields',
                            'data' => ['fields_to_update' => $fieldsToUpdate]
                        ],
                        'database' => [
                            'user_id' => $complaint->user->id,
                            'title' => 'Additional Information Required',
                            'message' => 'We need more information about your complaint',
                            'type' => 'info_request',
                            'complaint_id' => $complaintId,
                            'metadata' => [
                                'requested_info' => $fieldsToUpdate,
                                'requested_by' => 'Officer Name',
                                'reason' => $reason,
                            ],
                        ]
                    ]
                ];
            },
            // After-commit action
            function ($result) {
                $this->sendAfterCommitNotifications($result['notification_data']);
                return $result['response']; // Return original response
            }
        );
    }

    public function submitUpdatedComplaint($complaintId, $userId, $data, $attachments = [])
    {
        return $this->executeTransaction(function () use ($complaintId, $userId, $data, $attachments) {
            $complaint = $this->repo->findById($complaintId);

            if (!$complaint) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Complaint not found',
                    'data' => null
                ];
            }

            if ($complaint->user_id != $userId) {
                return [
                    'success' => false,
                    'status' => 403,
                    'message' => 'You are not authorized to update this complaint',
                    'data' => null
                ];
            }

            $updateData = [
                'title'       => $data['title'] ?? $complaint->title,
                'description' => $data['description'] ?? $complaint->description,
                'location'    => $data['location'] ?? $complaint->location,
                'status'      => 'new',
                'is_locked'   => false,
                'locked_at'   => null,
                'locked_by'   => null,
            ];

            $complaint->update($updateData);

            if (!empty($attachments)) {
                $this->saveUpdatedAttachments($complaint, $attachments);
            }

            ComplaintLog::create([
                'complaint_id' => $complaintId,
                'user_id'      => $userId,
                'action'       => 'citizen_updated_complaint',
                'notes'        => 'Citizen updated the complaint with new data and/or attachments'
            ]);

            return [
                'success' => true,
                'status'  => 200,
                'message' => 'Complaint updated successfully',
                'data'    => $complaint->fresh()
            ];
        });
    }

    private function saveUpdatedAttachments($complaint, $files)
    {
        foreach ($files as $file) {
            $extension = $file->getClientOriginalExtension();
            $fileName = uniqid() . '.' . $extension;

            $folderPath = public_path("complaints/{$complaint->id}");

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $file->move($folderPath, $fileName);

            $fileUrl = url("complaints/{$complaint->id}/{$fileName}");

            ComplaintAttachment::create([
                'complaint_id'  => $complaint->id,
                'file_path'     => $fileUrl,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
            ]);
        }
    }


    public function submitUpdatedComplaintByCitizen($complaintId, $userId, $data, $attachments = [])
    {
        return $this->executeTransaction(function () use ($complaintId, $userId, $data, $attachments) {
            $complaint = $this->repo->findById($complaintId);

            if (!$complaint) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Complaint not found',
                    'data' => null
                ];
            }

            if ($complaint->user_id != $userId) {
                return [
                    'success' => false,
                    'status' => 403,
                    'message' => 'You are not authorized to update this complaint',
                    'data' => null
                ];
            }

            if ($complaint->status !== 'in_progress') {
                return [
                    'success' => false,
                    'status' => 409,
                    'message' => 'Complaint must be in progress to be updated by citizen',
                    'data' => null
                ];
            }

            $updateData = $data;
            $updateData['status'] = 'in_progress'; // تبقى in_progress
            $updateData['is_locked'] = false;
            $updateData['locked_at'] = null;
            $updateData['locked_by'] = null;

            $complaint->update($updateData);

            if (!empty($attachments)) {
                $this->saveUpdatedAttachments($complaint, $attachments);
            }

            ComplaintLog::create([
                'complaint_id' => $complaintId,
                'user_id'      => $userId,
                'action'       => 'citizen_updated_complaint',
                'notes'        => 'Citizen updated complaint while in progress'
            ]);

            return [
                'success' => true,
                'status'  => 200,
                'message' => 'Complaint updated successfully',
                'data'    => $complaint->fresh()
            ];
        });
    }


    public function getComplaintForEmployee(int $complaintId, User $employee): array
    {
        $complaint = Complaint::with([
            'user:id,name,email',
            'governorate:id,name',
            'department:id,name',
            'attachments',
            'logs.user:id,name'
        ])->find($complaintId);

        if (!$complaint) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'Complaint not found',
                'data'    => null
            ];
        }

        // 🔒 صلاحيات الموظف
        // 🔒 إذا كان Employee فقط نطبق التحقق
        if ($employee->role === 'employee') {
            if (
                $complaint->governorate_id !== $employee->governorate_id ||
                $complaint->department_id  !== $employee->department_id
            ) {
                return [
                    'success' => false,
                    'status'  => 403,
                    'message' => 'You are not authorized to view this complaint',
                    'data'    => null
                ];
            }
        }

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Complaint details retrieved successfully',
            'data'    => $this->formatComplaintDetails($complaint)
        ];
    }

    private function formatComplaintDetails(Complaint $c): array
    {
        return [
            'id' => $c->id,
            'reference_number' => $c->reference_number,
            'title' => $c->title,
            'description' => $c->description,
            'status' => $c->status,

            'citizen' => [
                'name'  => $c->user->name,
                'email' => $c->user->email,
            ],

            'governorate' => $c->governorate->name,
            'department'  => $c->department->name,

            'attachments' => $c->attachments->map(fn($a) => [
                'id' => $a->id,
                'file_url' => $a->file_path,
                'original_name' => $a->original_name,
            ]),

            'timeline' => $c->logs->map(fn($log) => [
                'action' => $log->action,
                'notes'  => $log->notes,
                'by'     => $log->user?->name,
                'date'   => $log->created_at->format('Y-m-d H:i'),
            ]),

            'created_at' => $c->created_at->format('Y-m-d H:i'),
        ];
    }

    public function home(): array
    {
        $user = Auth::user();

        $complaints = Complaint::with(['governorate', 'department'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();


        $summary = [
            'total'       => $complaints->count(),
            'new'         => $complaints->where('status', 'new')->count(),
            'in_progress' => $complaints->where('status', 'in_progress')->count(),
            'resolved'    => $complaints->where('status', 'resolved')->count(),
            'rejected'    => $complaints->where('status', 'rejected')->count(),
            'needs_update'=> $complaints->where('status', 'needs_update')->count(),
        ];

        $list = $complaints
            ->where('status', 'needs_update')
            ->map(function ($c) {
                return [
                    'id'               => $c->id,
                    'reference_number' => $c->reference_number,
                    'title'            => $c->title,
                    'status'           => $c->status,
                    'governorate'      => $c->governorate?->name,
                    'department'       => $c->department?->name,
                    'created_at'       => $c->created_at,
                ];
            })
            ->values();

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Citizen home data retrieved successfully',
            'data'    => [
                'user' => [
                    'id'   => $user->id,
                    'name' => $user->name,
                    'email'=> $user->email,
                ],
                'summary'    => $summary,
                'complaints needs update' => $list,
            ]
        ];
    }
}
