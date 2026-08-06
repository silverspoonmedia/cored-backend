<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Internal vTual registry — orphan seed + pending claims (Root/Admin/Moderator).
 */
class AdminRegistryController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $pending = $this->api()->get('vtual/claims/pending');
        if (! $pending->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($pending));
        }

        $pendingData = collect($pending->json('data', []));
        $now = now();

        return $this->webView('vtual.admin.registry', [
            'pending' => $pendingData,
            'pendingCount' => $pendingData->count(),
            'expiringSoonCount' => $pendingData->filter(function (array $claim) use ($now): bool {
                $expires = $claim['expires_at'] ?? null;
                if (! is_string($expires) || $expires === '') {
                    return false;
                }

                try {
                    $expiresAt = \Illuminate\Support\Carbon::parse($expires);

                    return $expiresAt->isFuture() && $expiresAt->lte($now->copy()->addHours(24));
                } catch (\Throwable) {
                    return false;
                }
            })->count(),
            'acceptAnywayEnabled' => (bool) setting_config('vtual.claim.accept_anyway_enabled', false),
        ]);
    }

    public function showClaim(string $claim): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/claims/'.$claim);

        if (! $response->successful()) {
            return redirect()->route('vtual.admin.registry')->with('error', $this->apiMessage($response));
        }

        return $this->webView('vtual.admin.claims.show', [
            'claim' => $response->json('data', []),
            'acceptAnywayEnabled' => (bool) setting_config('vtual.claim.accept_anyway_enabled', false),
        ]);
    }

    public function acceptAnyway(Request $request, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/claims/'.$claim.'/accept-anyway');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.admin.registry')
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_accepted'))),
            __('web/vtual.ajax.claim_accepted'),
        );
    }

    public function storeOrphan(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/creators/orphan', $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            function ($resp) {
                $id = $resp->json('data.id');

                return (is_string($id) && $id !== '')
                    ? redirect()->route('vtual.profiles.show', $id)
                        ->with('success', $this->apiMessage($resp, __('web/vtual.ajax.orphan_created')))
                    : redirect()->route('vtual.admin.registry')
                        ->with('success', $this->apiMessage($resp, __('web/vtual.ajax.orphan_created')));
            },
            __('web/vtual.ajax.orphan_created'),
        );
    }
}
