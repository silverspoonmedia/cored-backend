<?php

namespace App\Domain\Navigation\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web navigation-menu management — a pure consumer of the versioned API.
 *
 * Every list, mutation and recovery forwards to `/api/v1/navigation/menus`
 * (see {@see \App\Domain\Navigation\Http\Controllers\API\V1\MenuController}); the
 * structural `h → p → c` validation, cycle guards and RBAC all run there.
 */
class MenuController extends Controller
{
    use ConsumesApi;

    /**
     * Filtered navigation tree for sidebar live-search (proxies the API).
     */
    public function navigationTree(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();
        $layout = $request->string('layout')->trim()->toString();
        $params = array_filter([
            'q' => $query !== '' ? $query : null,
            'layout' => $layout !== '' ? $layout : null,
        ]);
        $response = $this->api()->get('navigation/menus', $params);

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }

    public function index(Request $request): View|RedirectResponse
    {
        $layout = $this->resolveLayout($request->query('layout'));

        $response = $this->api()->get('navigation/menus/list', ['layout' => $layout]);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('menus.index', [
            'menus' => $response->json('data', []),
            'selectedLayout' => $layout,
            'layoutOptions' => $this->layoutOptions(),
        ]);
    }

    public function trashed(Request $request): View|RedirectResponse
    {
        $layout = $this->resolveLayout($request->query('layout'));

        $response = $this->api()->get('navigation/menus/trashed', ['layout' => $layout]);

        if (! $response->successful()) {
            return redirect()->route('menus.index', ['layout' => $layout])->with('error', $this->apiMessage($response));
        }

        return $this->webView('menus.trashed', [
            'menus' => $response->json('data', []),
            'selectedLayout' => $layout,
            'layoutOptions' => $this->layoutOptions(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        return $this->renderForm('menus.create', [
            'layout' => $this->resolveLayout($request->query('layout')),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('navigation/menus', $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.index', [
                'layout' => $this->resolveLayout($request->input('layout')),
            ])->with('success', __('api/navigation.created')),
            __('api/navigation.created'),
        );
    }

    public function edit(string $menu): View|RedirectResponse
    {
        $response = $this->api()->get("navigation/menus/{$menu}");

        if (! $response->successful()) {
            return redirect()->route('menus.index')->with('error', $this->apiMessage($response));
        }

        return $this->renderForm('menus.edit', $response->json('data', []));
    }

    public function update(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("navigation/menus/{$menu}", $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', __('api/navigation.updated')),
            __('api/navigation.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("navigation/menus/{$menu}", ['cascade' => $request->boolean('cascade')]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.index', [
                'layout' => $this->resolveLayout($request->query('layout', $request->input('layout'))),
            ])->with('success', __('api/navigation.deleted')),
            __('api/navigation.deleted'),
        );
    }

    public function restore(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("navigation/menus/{$menu}/restore");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.trashed', [
                'layout' => $this->resolveLayout($request->query('layout', $request->input('layout'))),
            ])->with('success', __('api/navigation.restored')),
            __('api/navigation.restored'),
        );
    }

    /**
     * Build the create/edit form payload.
     *
     * @param  array<string, mixed>  $menu  Menu detail from the API (empty on create).
     */
    private function renderForm(string $view, array $menu = []): View|RedirectResponse
    {
        $layout = $menu['layout'] ?? MenuLayout::default();

        return $this->webView($view, [
            'menu' => $menu !== [] ? $menu : null,
            'listLayout' => $layout,
            'hydratedLookups' => $this->hydratedLookups($menu, $layout),
        ]);
    }

    /**
     * @param  array<string, mixed>  $menu
     * @return array{menus: array<string, string>, users: array<string, string>, roles: array<string, string>}
     */
    private function hydratedLookups(array $menu, string $layout): array
    {
        if (! isset($menu['id'])) {
            return ['menus' => [], 'users' => [], 'roles' => []];
        }

        $menuIds = array_values(array_filter([(string) ($menu['parent_id'] ?? '')]));
        $userIds = array_values(array_unique(array_merge(
            $menu['user_include_ids'] ?? [],
            $menu['user_exclude_ids'] ?? [],
        )));
        $roleIds = $menu['role_ids'] ?? [];

        return [
            'menus' => $this->lookupOptions('lookups/menus', [
                'ids' => $menuIds,
                'layout' => $layout,
                'exclude' => $menu['id'] ?? null,
            ]),
            'users' => $this->lookupOptions('lookups/users', ['ids' => $userIds]),
            'roles' => $this->lookupOptions('lookups/roles', ['ids' => $roleIds]),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function lookupOptions(string $uri, array $query): array
    {
        $ids = $query['ids'] ?? [];

        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $response = $this->api()->get($uri, $query);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data.results', []))
            ->mapWithKeys(fn (array $row): array => [(string) $row['id'] => (string) $row['text']])
            ->all();
    }

    private function resolveLayout(?string $layout): string
    {
        if ($layout !== null && MenuLayout::isValid($layout)) {
            return $layout;
        }

        return MenuLayout::default();
    }

    /**
     * @return array<string, string>
     */
    private function layoutOptions(): array
    {
        return collect(MenuLayout::all())
            ->mapWithKeys(fn (string $layout): array => [$layout => __('web/menus.form.layouts.'.$layout)])
            ->all();
    }
}
