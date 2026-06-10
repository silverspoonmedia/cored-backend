<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Resends the email verification link for the signed-in user. Reuses
 * {@see EmailVerificationService} (throws when already verified).
 */
class EmailVerificationController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(
        private readonly EmailVerificationService $verification,
    ) {}

    public function resend(): RedirectResponse
    {
        return $this->handleDomain(function (): RedirectResponse {
            /** @var User $user */
            $user = Auth::guard('api')->user();

            $this->verification->sendNotification($user);

            return back()->with('success', 'Tautan verifikasi telah dikirim ke email Anda.');
        });
    }
}
