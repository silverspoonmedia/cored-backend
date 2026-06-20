<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileTwoFactorController extends Controller
{
    use ConsumesApi;

    public function edit(Request $request): View
    {
        $profile = $this->api()->get('auth/me')->json('data', []);

        return $this->webView('profile.two-factor', [
            'profile' => $profile,
            'setup' => $request->session()->get('two_factor_setup'),
        ]);
    }

    public function setup(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/two-factor/setup');

        return $this->respondFromApi(
            $request,
            $response,
            function (Response $response) use ($request): RedirectResponse {
                $request->session()->put('two_factor_setup', $response->json('data', []));

                return redirect()->route('profile.two-factor.edit');
            },
            __('web/auth.two_factor_setup_started'),
        );
    }

    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/two-factor/confirm', [
            'code' => $request->input('code'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            function (Response $response) use ($request): RedirectResponse {
                $request->session()->forget('two_factor_setup');

                return redirect()->route('profile.two-factor.edit')->with('success', __('web/auth.two_factor_enabled'));
            },
            __('web/auth.two_factor_enabled'),
        );
    }

    public function disable(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('auth/two-factor/disable', [
            'code' => $request->input('code'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('profile.two-factor.edit')->with('success', __('web/auth.two_factor_disabled')),
            __('web/auth.two_factor_disabled'),
        );
    }
}
