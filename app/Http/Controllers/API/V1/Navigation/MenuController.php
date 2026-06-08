<?php

namespace App\Http\Controllers\API\V1\Navigation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Navigation\IndexMenuListRequest;
use App\Http\Requests\Navigation\StoreMenuRequest;
use App\Http\Requests\Navigation\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\User;
use App\Services\Navigation\MenuService;
use App\Support\Navigation\MenuPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MenuService $menus,
    ) {}

    /**
     * Auth-aware navigation tree for the current request (guest or authenticated).
     */
    public function index(): JsonResponse
    {
        $tree = $this->menus->navigationTree($this->optionalActor());

        return $this->successResponse($tree, 'Navigation tree retrieved successfully.');
    }

    /**
     * Hierarchically sorted flat list for management screens.
     */
    public function list(IndexMenuListRequest $request): JsonResponse
    {
        $rows = MenuPresenter::presentAdminCollection(
            $this->menus->adminList($request->validated()['load_type'] ?? null),
        );

        return $this->successResponse($rows, 'Menus retrieved successfully.');
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $menu = $this->menus->create($request->validated());

        return $this->successResponse(
            MenuPresenter::presentDetail($menu),
            'Menu created successfully.',
            201,
        );
    }

    public function show(Menu $menu): JsonResponse
    {
        return $this->successResponse(
            MenuPresenter::presentDetail($this->menus->detail($menu->id)),
            'Menu retrieved successfully.',
        );
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        $updated = $this->menus->update($menu, $request->validated());

        return $this->successResponse(
            MenuPresenter::presentDetail($updated),
            'Menu updated successfully.',
        );
    }

    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        $this->menus->delete($menu, $request->boolean('cascade'));

        return $this->successResponse(null, 'Menu deleted successfully.');
    }

    /**
     * Soft-deleted menus for the recovery screen.
     */
    public function trashed(IndexMenuListRequest $request): JsonResponse
    {
        $rows = MenuPresenter::presentTrashedCollection(
            $this->menus->trashedList($request->validated()['load_type'] ?? null),
        );

        return $this->successResponse($rows, 'Trashed menus retrieved successfully.');
    }

    /**
     * Restore a soft-deleted menu and its trashed subtree. `{menu}` is bound
     * with `withTrashed()` so the trashed record resolves.
     */
    public function restore(Menu $menu): JsonResponse
    {
        $this->menus->restore($menu);

        return $this->successResponse(
            MenuPresenter::presentDetail($this->menus->detail($menu->id)),
            'Menu restored successfully.',
        );
    }

    private function optionalActor(): ?User
    {
        $user = Auth::guard('api')->user();

        return $user instanceof User ? $user : null;
    }
}
