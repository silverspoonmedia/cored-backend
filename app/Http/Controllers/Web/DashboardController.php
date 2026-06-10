<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        // The identity payload comes from the API at GET /api/v1/auth/me.
        $response = $this->api()->get('auth/me');

        if (! $response->successful()) {
            return redirect()
                ->route('login')
                ->withCookie(AuthCookie::forget())
                ->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        return view('dashboard', [
            'profile' => $response->json('data', []),
        ]);
    }
}
