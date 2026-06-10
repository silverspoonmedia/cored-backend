<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\RedirectResponse;

/**
 * Resends the email verification link for the signed-in user by forwarding to
 * `POST /api/v1/auth/email/verification-notification` (the API throws when the
 * address is already verified).
 */
class EmailVerificationController extends Controller
{
    use ConsumesApi;

    public function resend(): RedirectResponse
    {
        $response = $this->api()->post('auth/email/verification-notification');

        return $this->redirectFromApi(
            $response,
            fn (): RedirectResponse => back()->with('success', 'Tautan verifikasi telah dikirim ke email Anda.'),
        );
    }
}
