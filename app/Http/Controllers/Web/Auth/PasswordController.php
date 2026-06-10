<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Authenticated self-service password change. Forwards to
 * `PUT /api/v1/auth/password` (the API verifies the current password before
 * updating); the JWT travels as a Bearer header from the auth cookie.
 */
class PasswordController extends Controller
{
    use ConsumesApi;

    public function edit(): View
    {
        return view('profile.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $response = $this->api()->put('auth/password', $request->except(['_token', '_method']));

        return $this->redirectFromApi(
            $response,
            fn (): RedirectResponse => back()->with('success', 'Kata sandi berhasil diperbarui.'),
        );
    }
}
