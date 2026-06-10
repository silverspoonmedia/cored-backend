<?php

namespace App\Http\Controllers\Web\Navigation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Requests\Navigation\StoreMenuRequest;
use App\Http\Requests\Navigation\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web mirror of the API {@see \App\Http\Controllers\API\V1\Navigation\MenuController}.
 * Reuses {@see MenuService} and the same store/update form requests (structural
 * `h \u2192 p \u2192 c` validation, cycle guards, flag-compatibility checks). Permission
 * gating (`view menu`, `manage menu`, `restore menu`) is applied on the routes.
 */
class MenuController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(
        private readonly MenuService $menus,
    ) {}

    public function index(): View
    {
        return view('menus.index', [
            'menus' => $this->menus->adminList(null),
        ]);
    }

    public function create(): View
    {
        return view('menus.create', $this->formData());
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        return $this->handleDomain(function () use ($request): RedirectResponse {
            $this->menus->create($request->validated());

            return redirect()->route('menus.index')->with('success', 'Menu berhasil dibuat.');
        });
    }

    public function edit(Menu $menu): View
    {
        $menu->load(['roles', 'includedUsers', 'excludedUsers']);

        return view('menus.edit', array_merge($this->formData($menu), [
            'menu' => $menu,
        ]));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        return $this->handleDomain(function () use ($request, $menu): RedirectResponse {
            $this->menus->update($menu, $request->validated());

            return redirect()->route('menus.index')->with('success', 'Menu berhasil diperbarui.');
        });
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        return $this->handleDomain(function () use ($request, $menu): RedirectResponse {
            $this->menus->delete($menu, $request->boolean('cascade'));

            return redirect()->route('menus.index')->with('success', 'Menu berhasil dihapus.');
        });
    }

    public function trashed(): View
    {
        return view('menus.trashed', [
            'menus' => $this->menus->trashedList(null),
        ]);
    }

    public function restore(Menu $menu): RedirectResponse
    {
        return $this->handleDomain(function () use ($menu): RedirectResponse {
            $this->menus->restore($menu);

            return redirect()->route('menus.trashed')->with('success', 'Menu berhasil dipulihkan.');
        });
    }

    /**
     * Shared option lists for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(?Menu $current = null): array
    {
        $parents = collect($this->menus->adminList(null))
            ->when($current !== null, fn ($menus) => $menus->reject(fn (Menu $m) => $m->id === $current->id))
            ->values();

        return [
            'allMenus' => $parents,
            'roles' => Role::query()->orderBy('level')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }
}
