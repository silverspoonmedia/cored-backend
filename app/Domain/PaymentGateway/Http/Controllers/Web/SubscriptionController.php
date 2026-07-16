<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Http\Controllers\API\V1\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web subscription management — a pure consumer of the versioned API.
 *
 * Forwards to `/api/v1/subscriptions` (see {@see ApiSubscriptionController})
 * via {@see ConsumesApi} and renders Blade views or redirects.
 */
class SubscriptionController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('subscriptions.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('subscriptions', array_merge(
            $request->query(),
            ['display_mode' => 'datatable'],
        ));

        if (! $response->successful()) {
            return response()->json([
                'draw' => (int) $request->query('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        $payload = $response->json();
        $payload['data'] = array_map(
            fn (array $row): array => $this->mapRowActions($row),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    public function create(): View
    {
        return $this->webView('subscriptions.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('subscriptions', $this->apiPayload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('subscriptions.index')->with('success', __('api/payments.subscriptions.created')),
            __('api/payments.subscriptions.created'),
        );
    }

    public function show(string $subscription): View|RedirectResponse
    {
        $response = $this->api()->get("subscriptions/{$subscription}");

        if (! $response->successful()) {
            return redirect()->route('subscriptions.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('subscriptions.show', [
            'subscription' => $response->json('data', []),
        ]);
    }

    public function edit(string $subscription): View|RedirectResponse
    {
        $response = $this->api()->get("subscriptions/{$subscription}");

        if (! $response->successful()) {
            return redirect()->route('subscriptions.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('subscriptions.edit', [
            'subscription' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $subscription): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("subscriptions/{$subscription}", $this->apiPayload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('subscriptions.index')->with('success', __('api/payments.subscriptions.updated')),
            __('api/payments.subscriptions.updated'),
        );
    }

    public function destroy(Request $request, string $subscription): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("subscriptions/{$subscription}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('subscriptions.index')->with('success', __('api/payments.subscriptions.deleted')),
            __('api/payments.subscriptions.deleted'),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRowActions(array $row): array
    {
        $allowed = array_column($row['actions'] ?? [], 'key');
        $id = $row['id'] ?? null;
        $actions = [];

        if ($id !== null && in_array('show', $allowed, true)) {
            $actions[] = [
                'key' => 'show',
                'label' => __('ui.datatable.view'),
                'method' => 'GET',
                'url' => route('subscriptions.show', $id),
                'style' => 'info',
                'icon' => 'bi bi-eye',
            ];
        }

        if ($id !== null && in_array('edit', $allowed, true)) {
            $actions[] = [
                'key' => 'edit',
                'label' => __('ui.datatable.edit'),
                'method' => 'GET',
                'url' => route('subscriptions.edit', $id),
                'style' => 'primary',
                'icon' => 'bi bi-pencil',
            ];
        }

        if ($id !== null && in_array('delete', $allowed, true)) {
            $actions[] = [
                'key' => 'delete',
                'label' => __('ui.datatable.delete'),
                'method' => 'DELETE',
                'url' => route('subscriptions.destroy', $id),
                'style' => 'danger',
                'icon' => 'bi bi-trash',
                'confirm' => __('web/payments.confirm.delete_subscription'),
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
