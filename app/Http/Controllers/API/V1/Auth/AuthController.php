<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
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

        return $this->successResponse($payload, 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login($request->validated());

        return $this->successResponse($payload, 'Login successful.');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(
            $this->authService->me(),
            'Authenticated user retrieved successfully.',
        );
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->authService->refresh();

        return $this->successResponse($payload, 'Token refreshed successfully.');
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
