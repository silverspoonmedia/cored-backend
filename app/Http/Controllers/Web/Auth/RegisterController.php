<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Browser self-registration. Forwards to `POST /api/v1/auth/register` (the API
 * validates, assigns the default role, sends the verification email and issues
 * a JWT), then signs the user in via the auth cookie.
 */
class RegisterController extends Controller
{
    use ConsumesApi;
    use IssuesAuthCookie;

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/register', $request->except(['_token']));

        return $this->respondFromApi(
            $request,
            $response,
            function (Response $r): RedirectResponse {
                session()->flash('success', __('web/auth.register_success'));

                return $this->redirectAuthenticated($r->json('data', []));
            },
            __('web/auth.register_success'),
        );
    }
}
