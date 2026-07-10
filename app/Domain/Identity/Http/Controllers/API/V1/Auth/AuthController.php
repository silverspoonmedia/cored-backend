<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Identity\Http\Requests\Auth\LoginRequest;
use App\Domain\Identity\Http\Requests\Auth\RegisterRequest;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $this->authService->register($request->validated());

        return $this->successResponse($payload, __('api/auth.register_success'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login($request->validated());

        return $this->successResponse($payload, __('api/auth.login_success'));
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(
            $this->authService->me(),
            __('api/auth.me_success'),
        );
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->authService->refresh();

        return $this->successResponse($payload, __('api/auth.refresh_success'));
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->successResponse(null, __('api/auth.logout_success'));
    }
}
