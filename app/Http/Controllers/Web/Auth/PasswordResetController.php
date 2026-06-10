<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Forgot-password (request a reset link) and reset (set a new password) screens.
 * Reuses {@see PasswordResetService} and the same form requests as the API.
 */
class PasswordResetController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(
        private readonly PasswordResetService $passwords,
    ) {}

    public function showLinkRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->sendResetLink($request->validated()['email']);

        // Generic message: never reveal whether the email exists.
        return back()->with('success', 'Jika email terdaftar, kami telah mengirim tautan reset kata sandi.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        return $this->handleDomain(function () use ($request): RedirectResponse {
            $this->passwords->reset($request->validated());

            return redirect()
                ->route('login')
                ->with('success', 'Kata sandi berhasil diatur ulang. Silakan masuk.');
        });
    }
}
