<?php

namespace App\Domain\Identity\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use App\Support\Auth\AuthCookie;
use App\Support\Auth\UserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ConsumesApi;

    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        // The identity payload comes from the API at GET /api/v1/auth/me.
        $response = $this->api()->get('auth/me');

        // Only a real auth failure clears the JWT cookie. Transport/5xx must not
        // look like "session expired" — the user is still signed in.
        if ($response->status() === 401) {
            if ($request->expectsJson()) {
                $message = __('web/session.expired');
                $request->session()->flash('error', $message);

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'redirect' => route('login'),
                ], 401)->withCookie(AuthCookie::forget());
            }

            return redirect()
                ->route('login')
                ->withCookie(AuthCookie::forget())
                ->with('error', __('web/session.expired'));
        }

        if (! $response->successful()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $this->apiMessage($response),
                ], $response->status() >= 400 ? $response->status() : 400);
            }

            // Keep auth_token; surface API/transport error without fake logout.
            // Fall back to the already-authenticated guard user so the view still renders.
            session()->flash('error', $this->apiMessage($response));

            $user = Auth::guard('api')->user();

            return $this->webView('dashboard', [
                'profile' => $user ? UserPresenter::present($user) : [],
            ]);
        }

        $profile = $response->json('data', []);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $profile,
            ]);
        }

        return $this->webView('dashboard', [
            'profile' => $profile,
        ]);
    }
}
