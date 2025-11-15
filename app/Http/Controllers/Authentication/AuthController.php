<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\SignUpRequest;
use App\Http\Requests\SignInRequest;
use App\Services\Authentication\AuthService;
use App\Http\Responses\Response;
use Throwable;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function signUp(SignUpRequest $signUpRequest)
    {
        try {
            $data = $this->authService->signup($signUpRequest);
            return Response::success($data['message'], $data['user']);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            return Response::error($message);
        }
    }

    public function signIn(SignInRequest $signInRequest)
    {
        try {
            $data = $this->authService->signin($signInRequest);
            return Response::success($data['message'], $data['user'], $data['status']);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            return Response::error($message);
        }
    }

    public function signOut()
    {
        try {
            $data = $this->authService->signout();
            return Response::success($data['message']);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            return Response::error($message);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $data = $this->authService->updateProfile($request);
            return Response::success($data['message'], $data['profile']);
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage(), 404);
        }
    }
    public function createStaffAccount(Request $request)
    {
        try {
            $data = $this->authService->createStaffAccount($request);
            return Response::success($data['message'], $data['profile']);
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage(), 404);
        }
    }
}
