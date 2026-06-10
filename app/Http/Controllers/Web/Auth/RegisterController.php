<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Browser self-registration. Reuses {@see RegisterRequest} and
 * {@see AuthService::register()} (assigns the default role, sends the
 * verification email), then signs the user in via the auth cookie.
 */
class RegisterController extends Controller
{
    use HandlesDomainErrors;
    use IssuesAuthCookie;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        return $this->handleDomain(function () use ($request): RedirectResponse {
            $payload = $this->authService->register($request->validated());

            session()->flash('success', 'Registrasi berhasil. Kami telah mengirim tautan verifikasi ke email Anda.');

            return $this->redirectAuthenticated($payload);
        });
    }
}
