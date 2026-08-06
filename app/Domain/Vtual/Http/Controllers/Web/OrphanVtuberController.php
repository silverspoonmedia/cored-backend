<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Internal orphan vTuber admin — pure consumer of `/api/v1/vtual/creators/orphans`.
 */
class OrphanVtuberController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('vtual.orphans.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('vtual/creators/orphans', array_merge(
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
        return $this->webView('vtual.orphans.create', [
            'creator' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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
            function () use ($response): RedirectResponse {
                $id = $response->json('data.id');

                return redirect()->route('vtual.orphans.edit', $id)
                    ->with('success', $this->apiMessage($response, __('api/vtual.creator.orphan_created')));
            },
            __('api/vtual.creator.orphan_created'),
        );
    }

    public function edit(string $creator): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/creators/orphans/'.$creator);

        if (! $response->successful()) {
            return redirect()->route('vtual.orphans.index')->with('error', $this->apiMessage($response));
        }

        $platforms = $this->api()->get('vtual/platforms');

        return $this->webView('vtual.orphans.edit', [
            'creator' => $response->json('data', []),
            'platforms' => $platforms->successful() ? collect($platforms->json('data', [])) : collect(),
        ]);
    }

    public function storeChannel(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/creators/'.$creator.'/channels', $this->apiPayload($request, [
            'platform_key',
            'profile',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('api/vtual.channel.registered_pending'))),
            __('api/vtual.channel.registered_pending'),
            stayOnPageOnSuccess: true,
        );
    }

    public function update(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->patch('vtual/creators/'.$creator, $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('api/vtual.creator.updated'))),
            __('api/vtual.creator.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete('vtual/creators/'.$creator);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('vtual.orphans.index')
                ->with('success', $this->apiMessage($response, __('api/vtual.creator.archived')))
                ->with('datatable_reload', 'vtual-orphans-table'),
            __('api/vtual.creator.archived'),
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

        if ($id !== null && in_array('edit', $allowed, true)) {
            $actions[] = [
                'key' => 'edit',
                'label' => __('ui.datatable.edit'),
                'method' => 'GET',
                'url' => route('vtual.orphans.edit', $id),
                'style' => 'info',
                'icon' => 'bi bi-pencil',
            ];
        }

        if ($id !== null && in_array('delete', $allowed, true)) {
            $actions[] = [
                'key' => 'delete',
                'label' => __('ui.datatable.delete'),
                'method' => 'DELETE',
                'url' => route('vtual.orphans.destroy', $id),
                'style' => 'danger',
                'icon' => 'bi bi-trash',
                'confirm' => __('web/vtual-orphans.datatable.confirm_delete'),
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
