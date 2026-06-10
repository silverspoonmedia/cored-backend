<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Browser login. Reuses {@see LoginRequest} for validation and
 * {@see AuthService::login()} for the exact same JWT issuance the API performs,
 * then stores the token in the auth cookie.
 */
class LoginController extends Controller
{
    use HandlesDomainErrors;
    use IssuesAuthCookie;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        return $this->handleDomain(function () use ($request): RedirectResponse {
            $payload = $this->authService->login($request->validated());

            return $this->redirectAuthenticated($payload);
        });
    }

    public function destroy(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()
            ->route('login')
            ->withCookie(AuthCookie::forget())
            ->with('success', 'Anda telah keluar.');
    }
}
