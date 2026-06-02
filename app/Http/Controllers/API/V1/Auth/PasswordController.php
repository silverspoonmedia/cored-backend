<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->successResponse(
            null,
            'If that email address exists in our system, a password reset link has been sent.',
        );
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset($request->validated());

        return $this->successResponse(null, 'Password has been reset successfully.');
    }

    public function update(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $this->passwordResetService->changePassword($user->id, $request->validated());

        return $this->successResponse(null, 'Password updated successfully.');
    }
}
