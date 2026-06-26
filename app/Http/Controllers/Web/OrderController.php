<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\API\PlaygroundOrderController;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Support\Api\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web master-detail order management — a pure consumer of the playground API.
 *
 * No validation, totals, or transaction logic lives here: every action forwards
 * to `/api/playground/orders` (see {@see PlaygroundOrderController})
 * via {@see ConsumesApi}, and the controller only turns the JSON envelope into a
 * Blade view or a redirect. The atomic master-detail write happens behind the API.
 */
class OrderController extends Controller
{
    use ConsumesApi;

    /**
     * Playground orders live on the global API surface, not `/api/v1`, so point
     * the shared client at that prefix for every call in this controller.
     */
    protected function api(): ApiClient
    {
        return app(ApiClient::class)->withPrefix('api/playground');
    }

    public function index(): View
    {
        return $this->webView('orders.index');
    }

    /**
     * Server-side DataTables feed: proxy the API's datatable payload, then map
     * each row's allowed actions onto web routes. Empty table on API failure.
     */
    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('orders', array_merge(
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
        return $this->webView('orders.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->postMultipart(
            'orders',
            $this->payload($request),
            $request->file('attachments', []),
        );

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('orders.index')->with('success', __('api/playground-orders.created')),
            __('api/playground-orders.created'),
        );
    }

    public function show(string $order): View|RedirectResponse
    {
        $response = $this->api()->get("orders/{$order}");

        if (! $response->successful()) {
            return redirect()->route('orders.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('orders.show', [
            'order' => $response->json('data', []),
        ]);
    }

    public function edit(string $order): View|RedirectResponse
    {
        $response = $this->api()->get("orders/{$order}");

        if (! $response->successful()) {
            return redirect()->route('orders.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('orders.edit', [
            'order' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $order): RedirectResponse|JsonResponse
    {
        $response = $this->api()->putMultipart(
            "orders/{$order}",
            $this->payload($request),
            $request->file('attachments', []),
        );

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('orders.index')->with('success', __('api/playground-orders.updated')),
            __('api/playground-orders.updated'),
        );
    }

    public function destroy(Request $request, string $order): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("orders/{$order}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('orders.index')->with('success', __('api/playground-orders.deleted')),
            __('api/playground-orders.deleted'),
        );
    }

    /**
     * Mass-delete: forward the selected ids to the API's bulk endpoint. The API
     * runs the atomic batch delete + frozen-order rule; the web layer only
     * relays the id set and translates the JSON envelope (AJAX, no reload).
     */
    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete('orders/bulk', [
            'ids' => array_values((array) $request->input('ids', [])),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('orders.index')->with('success', $this->apiMessage($response)),
            $this->apiMessage($response),
            stayOnPageOnSuccess: true,
        );
    }

    /**
     * Non-file form fields to forward to the API (CSRF/method spoofing and the
     * uploaded files stripped — files are attached separately as multipart).
     * The `details`, `keep_attachments` and `attachments_managed` fields ride
     * along as-is; the API resolves the keep/drop semantics (an empty keep-list
     * cannot survive multipart, so the form sends `attachments_managed=1` and
     * the API treats an absent keep-list as "keep none").
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $request->except(['_token', '_method', 'attachments']);
    }

    /**
     * Rewrite the API's allowed-action keys onto clickable web routes.
     *
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
                'url' => route('orders.show', $id),
                'style' => 'info',
                'icon' => 'bi bi-eye',
            ];
        }

        if ($id !== null && in_array('edit', $allowed, true)) {
            $actions[] = [
                'key' => 'edit',
                'label' => __('ui.datatable.edit'),
                'method' => 'GET',
                'url' => route('orders.edit', $id),
                'style' => 'primary',
                'icon' => 'bi bi-pencil',
            ];
        }

        if ($id !== null && in_array('delete', $allowed, true)) {
            $actions[] = [
                'key' => 'delete',
                'label' => __('ui.datatable.delete'),
                'method' => 'DELETE',
                'url' => route('orders.destroy', $id),
                'style' => 'danger',
                'icon' => 'bi bi-trash',
                'confirm' => __('ui.datatable.confirm.delete_playground_order'),
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
