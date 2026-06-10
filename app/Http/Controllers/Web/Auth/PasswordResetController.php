<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
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
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/forgot-password', $request->only('email'));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', 'Jika email terdaftar, kami telah mengirim tautan reset kata sandi.'),
            'Jika email terdaftar, kami telah mengirim tautan reset kata sandi.',
            stayOnPageOnSuccess: true,
        );
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
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
            fn (): RedirectResponse => redirect()->route('login')->with('success', 'Kata sandi berhasil diatur ulang. Silakan masuk.'),
            'Kata sandi berhasil diatur ulang. Silakan masuk.',
        );
    }
}
