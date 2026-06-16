<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Forgot-password (request a reset link) and reset (set a new password) screens.
 * Both forward to `/api/v1/auth/*`; the API owns validation and the reset logic.
 */
class PasswordResetController extends Controller
{
    use ConsumesApi;

    public function showLinkRequest(): View
    {
        return $this->webView('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/forgot-password', $request->only('email'));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', __('web/auth.forgot_password_success')),
            __('web/auth.forgot_password_success'),
            stayOnPageOnSuccess: true,
        );
    }

    public function showReset(Request $request, string $token): View
    {
        return $this->webView('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/reset-password', $request->except(['_token']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('login')->with('success', __('web/auth.reset_password_success')),
            __('web/auth.reset_password_success'),
        );
    }
}
