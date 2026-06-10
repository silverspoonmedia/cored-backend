<?php

namespace App\Http\Controllers\Web\Navigation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web navigation-menu management — a pure consumer of the versioned API.
 *
 * Every list, mutation and recovery forwards to `/api/v1/navigation/menus`
 * (see {@see \App\Http\Controllers\API\V1\Navigation\MenuController}); the
 * structural `h → p → c` validation, cycle guards and RBAC all run there.
 */
class MenuController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('navigation/menus/list');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return view('menus.index', ['menus' => $response->json('data', [])]);
    }

    public function trashed(): View|RedirectResponse
    {
        $response = $this->api()->get('navigation/menus/trashed');

        if (! $response->successful()) {
            return redirect()->route('menus.index')->with('error', $this->apiMessage($response));
        }

        return view('menus.trashed', ['menus' => $response->json('data', [])]);
    }

    public function create(): View|RedirectResponse
    {
        return $this->renderForm('menus.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('navigation/menus', $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.index')->with('success', 'Menu berhasil dibuat.'),
            'Menu berhasil dibuat.',
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
            fn (): RedirectResponse => back()->with('success', 'Menu berhasil diperbarui.'),
            'Menu berhasil diperbarui.',
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("navigation/menus/{$menu}", ['cascade' => $request->boolean('cascade')]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.index')->with('success', 'Menu berhasil dihapus.'),
            'Menu berhasil dihapus.',
        );
    }

    public function restore(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("navigation/menus/{$menu}/restore");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('menus.trashed')->with('success', 'Menu berhasil dipulihkan.'),
            'Menu berhasil dipulihkan.',
        );
    }

    /**
     * Build the create/edit form payload.
     *
     * @param  array<string, mixed>  $menu  Menu detail from the API (empty on create).
     */
    private function renderForm(string $view, array $menu = []): View|RedirectResponse
    {
        $list = $this->api()->get('navigation/menus/list');

        if (! $list->successful()) {
            return redirect()->route('menus.index')->with('error', $this->apiMessage($list));
        }

        $currentId = $menu['id'] ?? null;

        $allMenus = collect($list->json('data', []))
            ->when($currentId !== null, fn ($items) => $items->reject(fn (array $m): bool => ($m['id'] ?? null) === $currentId))
            ->values();

        return view($view, [
            'menu' => $menu !== [] ? $menu : null,
            'allMenus' => $allMenus,
            // Reference option-lists for the role/user pickers. The API exposes
            // no full role/user catalogue endpoint, so these are read directly
            // (reference data only — no business logic lives here).
            'roles' => Role::query()->orderBy('level')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }
}
