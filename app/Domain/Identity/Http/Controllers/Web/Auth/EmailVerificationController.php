<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resends the email verification link for the signed-in user by forwarding to
 * `POST /api/v1/auth/email/verification-notification` (the API throws when the
 * address is already verified).
 */
class EmailVerificationController extends Controller
{
    use ConsumesApi;

    public function resend(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/email/verification-notification');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', __('web/auth.verification_resent')),
            __('web/auth.verification_resent'),
            stayOnPageOnSuccess: true,
        );
    }
}
