<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComplaintRequest;
use App\Services\ComplaintService;
use App\Helpers\ApiResponse;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    protected $service;
    protected $firebaseService;

    public function __construct(ComplaintService $service, FirebaseService $firebaseService)
    {
        $this->service = $service;
        $this->firebaseService = $firebaseService;
    }

    public function store(StoreComplaintRequest $request)
    {
        $files = $request->file('attachments', []);

        $data = $request->only([
            'title',
            'description',
            'department_id',
            'location',
            'governorate_id',

        ]);

        $complaint = $this->service->create($data, $files);

        return ApiResponse::success($complaint, 'Complaint created successfully', 201);
    }

    public function index()
    {
        return ApiResponse::success(
            $this->service->getUserComplaints(10),
            'Complaints fetched successfully'
        );
    }

    public function show($id)
    {
        $complaint = $this->service->getComplaintById($id);

        if (!$complaint) {
            return ApiResponse::error('Complaint not found', 404);
        }

        return ApiResponse::success($complaint, 'Complaint details fetched');
    }

    public function governorates()
    {
        return ApiResponse::success(
            $this->service->getGovernorates(),
            'Governorates fetched successfully'
        );
    }

    public function listGroupedDepartments()
    {
        return ApiResponse::success(
            $this->service->listDepartmentsGrouped(),
            'Departments grouped by governorates fetched'
        );
    }

    public function getDepartmentsByGovernorate(Request $request)
    {
        $request->validate([
            'governorate_id' => 'required|exists:governorates,id',
        ]);

        return ApiResponse::success(
            $this->service->getDepartmentsByGovernorate($request->governorate_id),
            'Departments fetched successfully'
        );
    }


   public function updateStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|string'
    ]);

    $response = $this->service->updateStatus(
        $id,
        Auth::id(),
        $request->status
    );

    if (isset($response['response'])) {
        $response = $response['response'];
    }

    return response()->json([
        'success' => $response['success'] ?? false,
        'message' => $response['message'] ?? null,
        'data'    => $response['data'] ?? null
    ], $response['status'] ?? 500);
}



    public function employeeComplaints()
    {
        $response = $this->service->getEmployeeComplaints();

        return response()->json([
            'status' => $response['status'],
            'message' => $response['message'],
            'data' => $response['data']
        ], $response['status']);
    }
    public function requestUpdate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'fields_to_update' => 'array|required'
        ]);

        $result = $this->service->requestUpdateFromEmployee(
            $id,
            Auth::id(),
            $request->reason,
            $request->fields_to_update ?? []
        );

        return response()->json($result, $result['response']['status']);}

    public function Update(Request $request, $id)
    {
        $request->validate([
            'title'       => 'sometimes|string',
            'description' => 'sometimes|string',
            'location'    => 'sometimes|string',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file'
        ]);

        $result = $this->service->submitUpdatedComplaint(
            $id,
            Auth::id(),
            $request->only(['title', 'description', 'location']),
            $request->file('attachments', [])
        );

        return response()->json($result, $result['status']);
    }

    public function submitUpdatedComplaint(Request $request, $id)
    {
        $result = $this->service->submitUpdatedComplaintByCitizen(
            $id,
            Auth::id(),
            $request->all(),
            $request->file('attachments', [])
        );

        return response()->json($result, $result['status']);
    }



    public function details(int $id, Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['employee', 'admin', ])) {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden',
                'data'    => null
            ], 403);
        }

        $result = $this->service->getComplaintForEmployee($id, $user);

        return response()->json($result, $result['status']);
    }

    public function home(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'citizen') {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden: citizen only',
                'data'    => null
            ], 403);
        }

        $result = $this->service->home();
        return response()->json($result, $result['status']);
    }


}
