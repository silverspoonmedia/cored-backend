<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only vTuber profile — consumer of `/api/v1/vtual/vtubers/{creator}`.
 */
class VtuberProfileController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('vtual.vtuber.index');
    }

    public function show(string $creator): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/vtubers/'.$creator);

        if (! $response->successful()) {
            abort($response->status() >= 400 ? $response->status() : 404, $this->apiMessage($response));
        }

        $profile = $response->json('data', []);

        if (Str::isUuid($creator) && ! empty($profile['slug']) && $profile['slug'] !== $creator) {
            return redirect()->route('vtual.vtuber.show', $profile['slug'], 301);
        }

        return $this->webView('vtual.vtuber.show', [
            'profile' => $profile,
        ]);
    }
}
