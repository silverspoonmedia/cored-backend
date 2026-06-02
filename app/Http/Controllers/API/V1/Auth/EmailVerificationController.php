<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\Auth\UserPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
    ) {}

    public function verify(string $id, string $hash): JsonResponse
    {
        $user = $this->emailVerificationService->verify($id, $hash);

        return $this->successResponse(
            UserPresenter::present($user),
            'Email verified successfully.',
        );
    }

    public function send(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $this->emailVerificationService->sendNotification($user);

        return $this->successResponse(
            null,
            'Verification link sent.',
        );
    }
}
