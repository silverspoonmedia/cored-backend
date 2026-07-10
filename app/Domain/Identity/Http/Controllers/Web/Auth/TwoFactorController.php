<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    use ConsumesApi;
    use IssuesAuthCookie;

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor_token')) {
            return redirect()->route('login');
        }

        return $this->webView('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/two-factor/login', [
            'two_factor_token' => $request->session()->get('two_factor_token'),
            'code' => $request->input('code'),
            'h-captcha-response' => $request->input('h-captcha-response'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            function (Response $response) use ($request): RedirectResponse {
                $request->session()->forget('two_factor_token');

                return $this->redirectAuthenticated($response->json('data', []));
            },
        );
    }
}
