<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\CreateEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Http\Requests\AdminListComplaintsRequest;
use App\Http\Requests\AdminReportRequest;
use App\Http\Requests\CreateEmployeeRequest as RequestsCreateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest as RequestsUpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Governorate;
use App\Services\AdminService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    protected $service;

    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    public function adminList(AdminListComplaintsRequest $request)
    {
        $result = $this->service->getComplaintsByFilters(
            $request->department_id,
            $request->governorate_id
        );

        return response()->json($result, $result['status']);
    }


    public function store(RequestsCreateEmployeeRequest $request)
    {
        $result = $this->service->createEmployee($request->validated());
        return response()->json($result['response'], $result['response']['status']);
    }

    public function update(RequestsUpdateEmployeeRequest $request, $id)
    {
        $result = $this->service->updateEmployee((int)$id, $request->validated());
        return response()->json($result['response'], $result['response']['status']);
    }

    // toggle active / inactive
    public function toggleActive(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden: admin only',
                'data'    => null
            ], 403);
        }

        $result = $this->service->toggleActive((int)$id);
        return response()->json($result['response'], $result['response']['status']);
    }

    public function destroy(Request $request, $id)
    {
        $currentAdminId = $request->user()->id ?? null;
        $result = $this->service->deleteEmployee((int)$id, $currentAdminId);
        return response()->json($result['response'], $result['response']['status']);
    }

    public function employeesByDepartmentGovernorate(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden: admin only',
                'data'    => null
            ], 403);
        }

        $request->validate([
            'governorate_id' => 'required|integer|exists:governorates,id',
            'department_id'  => 'required|integer|exists:departments,id',
        ]);

        $result = $this->service->getEmployeesByDepartmentGovernorate(
            (int)$request->governorate_id,
            (int)$request->department_id
        );

        return response()->json($result, $result['status']);
    }


    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden: admin only',
                'data'    => null
            ], 403);
        }

        $result = $this->service->getEmployeeWithComplaints((int)$id);
        return response()->json($result, $result['status']);
    }
    public function complaintsStats(AdminReportRequest $request)
    {
        $validated = $request->validated();

        $from = $validated['from'];
        $to   = $validated['to'];
        $gov  = $validated['governorate_id'] ;
        $dept = $validated['department_id'];

        $result = $this->service->complaintsSummary($from, $to, $gov, $dept);

        return response()->json($result, $result['status']);
    }
    public function stats(Request $request)
    {
        if (!$request->start_date || !$request->end_date) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'start_date and end_date are required',
                'data'    => null
            ], 422);
        }

        $filters = [
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
        ];

        $result = $this->service->getStats($filters);
        return response()->json($result, $result['status']);
    }
    public function exportComplaintsPdf(AdminReportRequest $request)
    {
        $validated = $request->validated();

        $from  = $validated['from'];
        $to    = $validated['to'];
        $gov   = $validated['governorate_id'] ?? null;
        $dept  = $validated['department_id'] ?? null;

        $stats = $this->service->complaintsSummary($from, $to, $gov, $dept);

        $data = $stats['data'];

        $data['filters']['governorate_name'] =
            $gov ? Governorate::find($gov)?->name ?? 'Unknown' : 'All';

        $data['filters']['department_name'] =
            $dept ? Department::find($dept)?->name ?? 'Unknown' : 'All';

        $pdf = Pdf::loadView('reports.complaints', $data)
            ->setPaper('A4', 'portrait');

        return $pdf->download('complaints-report.pdf');
    }
}
