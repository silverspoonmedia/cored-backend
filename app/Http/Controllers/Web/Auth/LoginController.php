<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Http\Controllers\Web\Controller;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
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
        return $this->webView('auth.login');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/login', $request->except(['_token']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (Response $r): RedirectResponse => $this->redirectAuthenticated($r->json('data', [])),
        );
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $this->api()->post('auth/logout');

        $redirect = redirect()
            ->route('login')
            ->withCookie(AuthCookie::forget())
            ->with('success', __('web/auth.logout_success'));

        if ($request->expectsJson()) {
            return $this->jsonFromRedirect($request, $redirect, __('web/auth.logout_success'));
        }

        return $redirect;
    }
}
