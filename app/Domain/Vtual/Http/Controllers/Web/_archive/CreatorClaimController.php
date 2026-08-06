<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * User claim surface — consumes /api/v1/vtual/claims/*.
 */
class CreatorClaimController extends Controller
{
    use ConsumesApi;

    /**
     * User: claimable orphans + my pending claims (no orphan create).
     */
    public function index(): View|RedirectResponse
    {
        $claimable = $this->api()->get('vtual/claims/claimable-creators');
        $mine = $this->api()->get('vtual/claims/mine');

        if (! $claimable->successful()) {
            return redirect()->route('vtual.index')->with('error', $this->apiMessage($claimable));
        }

        return $this->webView('vtual.claims.index', [
            'claimable' => collect($claimable->json('data', [])),
            'mine' => $mine->successful() ? collect($mine->json('data', [])) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/claims', $this->apiPayload($request, [
            'creator_id',
            'channel_id',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            function ($resp) {
                $id = $resp->json('data.id');

                return (is_string($id) && $id !== '')
                    ? redirect()->route('vtual.claims.show', $id)
                        ->with('success', $this->apiMessage($resp, __('web/vtual.ajax.claim_started')))
                    : redirect()->route('vtual.claims.index')
                        ->with('success', $this->apiMessage($resp, __('web/vtual.ajax.claim_started')));
            },
            __('web/vtual.ajax.claim_started'),
        );
    }

    public function show(string $claim): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/claims/'.$claim);

        if (! $response->successful()) {
            return redirect()->route('vtual.claims.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('vtual.claims.show', [
            'claim' => $response->json('data', []),
        ]);
    }

    public function verify(Request $request, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/claims/'.$claim.'/verify');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.claims.show', $claim)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_verified'))),
            __('web/vtual.ajax.claim_verified'),
            stayOnPageOnSuccess: true,
        );
    }

    public function cancel(Request $request, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/claims/'.$claim.'/cancel');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.claims.index')
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_cancelled'))),
            __('web/vtual.ajax.claim_cancelled'),
        );
    }
}
