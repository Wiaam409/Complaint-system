<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminListComplaintsRequest;
use App\Services\AdminService;
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
}
