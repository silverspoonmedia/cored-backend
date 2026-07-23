<?php

namespace App\Domain\Identity\Http\Controllers\Web;

use App\Http\Controllers\Web\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Support\Auth\AuthCookie;
use App\Support\Auth\UserPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        // The identity payload comes from the API at GET /api/v1/auth/me.
        $response = $this->api()->get('auth/me');

        // Only a real auth failure clears the JWT cookie. Transport/5xx must not
        // look like "session expired" — the user is still signed in.
        if ($response->status() === 401) {
            return redirect()
                ->route('login')
                ->withCookie(AuthCookie::forget())
                ->with('error', __('web/session.expired'));
        }

        if (! $response->successful()) {
            // Keep auth_token; surface API/transport error without fake logout.
            // Fall back to the already-authenticated guard user so the view still renders.
            session()->flash('error', $this->apiMessage($response));

            $user = Auth::guard('api')->user();

            return $this->webView('dashboard', [
                'profile' => $user ? UserPresenter::present($user) : [],
            ]);
        }

        return $this->webView('dashboard', [
            'profile' => $response->json('data', []),
        ]);
    }
}
