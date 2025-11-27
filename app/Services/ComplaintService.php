<?php

namespace App\Services;

use App\Repositories\ComplaintRepository;
use App\Repositories\GovernorateRepository;
use App\Repositories\DepartmentRepository;

use App\Models\ComplaintAttachment;
use App\Models\ComplaintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ComplaintService
{
    protected $repo;
    protected $governorates;
    protected $departments;
    protected $firebaseService;

    protected $cacheTTL = 300;

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



    public function create(array $data, $files = [])
    {
        return DB::transaction(function () use ($data, $files) {

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

    public function getUserComplaints()
    {
        $userId = Auth::id();

        return Cache::remember(
            "user_{$userId}_complaints",
            $this->cacheTTL,
            fn() => $this->repo->listByUser($userId)
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
        return DB::transaction(function () use ($complaintId, $employeeId, $status) {
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

            // UPDATE STATUS
            $complaint->update(['status' => $status]);
            $sent = $this->firebaseService->sendToToken(
                $complaint->user->fcm_token,
                'Status Update',
                'Your complaint status has been updated to ' . $status,
                ['type' => 'notice']
            );
            ComplaintLog::create([
                'complaint_id' => $complaintId,
                'user_id' => $employeeId,
                'action' => 'status_changed',
                'notes' => "Status changed to: {$status}"
            ]);

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Status updated successfully',
                'data' => $complaint
            ];
        });
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
                'message' => "You do not belong to this complaint’s department",
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
        $sent = $this->firebaseService->sendToToken(
            $complaint->user->fcm_token,
            'Fields update required',
            'You have to update the required fields',
            ['fields_to_update' => $fieldsToUpdate]
        );

        ComplaintLog::create([
            'complaint_id' => $complaintId,
            'user_id'      => $employeeId,
            'action'       => 'request_update',
            'notes'        => "Employee requested update: {$reason}"
        ]);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Update request sent successfully',
            'data' => $complaint
        ];
    }

    public function submitUpdatedComplaint($complaintId, $userId, $data, $attachments = [])
    {
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
    }


}
