<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Authenticated self-service password change. Reuses {@see ChangePasswordRequest}
 * and {@see PasswordResetService::changePassword()} (verifies the current
 * password before updating).
 */
class PasswordController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(
        private readonly PasswordResetService $passwords,
    ) {}

    public function edit(): View
    {
        return view('profile.password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        return $this->handleDomain(function () use ($request): RedirectResponse {
            /** @var User $user */
            $user = Auth::guard('api')->user();

            $this->passwords->changePassword($user->id, $request->validated());

            return back()->with('success', 'Kata sandi berhasil diperbarui.');
        });
    }
}
