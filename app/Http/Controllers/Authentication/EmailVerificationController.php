<?php

namespace App\Http\Controllers\Authentication;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Authentication\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationController extends Controller
{
    private EmailVerificationService $evs;

    public function __construct(EmailVerificationService $evs)
    {
        $this->evs = $evs;
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $data = $this->evs->verifyEmail($request);
            return Response::success($data['message']);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            return Response::error($message);
        }
    }

    public function resendVerificationCode(Request $request): JsonResponse
    {
        try {
            $data = $this->evs->resendVerificationCode($request);
            return Response::success($data['message']);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            return Response::error($message, 422);
        }
    }
}
