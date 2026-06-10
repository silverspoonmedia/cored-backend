<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Browser login. Forwards credentials to `POST /api/v1/auth/login` (the API
 * validates and issues the JWT), then stores the returned token in the HttpOnly
 * cookie. No auth logic lives here.
 */
class LoginController extends Controller
{
    use ConsumesApi;
    use IssuesAuthCookie;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $response = $this->api()->post('auth/login', $request->except(['_token']));

        return $this->redirectFromApi(
            $response,
            fn (Response $r): RedirectResponse => $this->redirectAuthenticated($r->json('data', [])),
        );
    }

    public function destroy(): RedirectResponse
    {
        // Best-effort: blacklist the token API-side; the cookie is cleared
        // regardless so the browser session always ends.
        $this->api()->post('auth/logout');

        return redirect()
            ->route('login')
            ->withCookie(AuthCookie::forget())
            ->with('success', 'Anda telah keluar.');
    }
}
