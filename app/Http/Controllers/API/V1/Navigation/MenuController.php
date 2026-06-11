<?php

namespace App\Http\Controllers\API\V1\Navigation;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Navigation\IndexMenuListRequest;
use App\Http\Requests\Navigation\StoreMenuRequest;
use App\Http\Requests\Navigation\UpdateMenuRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Http\Resources\Navigation\MenuDetailResource;
use App\Http\Resources\Navigation\MenuListResource;
use App\Http\Resources\Navigation\MenuTrashedResource;
use App\Models\Menu;
use App\Models\User;
use App\Services\Navigation\MenuService;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\DataTable\Resolvers\MenuRowActionResolver;
use App\Support\DataTable\Resolvers\MenuTrashedRowActionResolver;
use App\Support\Navigation\MenuPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly MenuService $menus,
        private readonly DataTableResponseFactory $dataTables,
        private readonly MenuRowActionResolver $rowActions,
        private readonly MenuTrashedRowActionResolver $trashedRowActions,
    ) {}

    /**
     * Auth-aware navigation tree for the current request (guest or authenticated).
     */
    public function index(): JsonResponse
    {
        $tree = $this->menus->navigationTree($this->optionalActor());

        return $this->successResponse($tree, __('api/navigation.tree_success'));
    }

    /**
     * Hierarchically sorted flat list for management screens.
     */
    public function list(IndexMenuListRequest $request): JsonResponse
    {
        $loadType = $request->validated()['load_type'] ?? null;
        $actor = $this->requiredActor();
        $menus = $this->menus->adminList($loadType);

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->successResponse(
                $this->resolveResource(MenuListResource::collection($menus)),
                __('api/navigation.list_success'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $menus,
                fn (Menu $menu) => $this->resolveResource(new MenuListResource($menu)),
                $this->rowActions,
                $actor,
            ),
        );
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $menu = $this->menus->create($request->validated());

        return $this->successResponse(
            MenuPresenter::presentDetail($menu),
            __('api/navigation.created'),
            201,
        );
    }

    public function show(Menu $menu): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new MenuDetailResource($this->menus->detail($menu->id))),
            __('api/navigation.show_success'),
        );
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        $updated = $this->menus->update($menu, $request->validated());

        return $this->successResponse(
            MenuPresenter::presentDetail($updated),
            __('api/navigation.updated'),
        );
    }

    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        $this->menus->delete($menu, $request->boolean('cascade'));

        return $this->successResponse(null, __('api/navigation.deleted'));
    }

    /**
     * Soft-deleted menus for the recovery screen.
     */
    public function trashed(IndexMenuListRequest $request): JsonResponse
    {
        $loadType = $request->validated()['load_type'] ?? null;
        $actor = $this->requiredActor();
        $menus = $this->menus->trashedList($loadType);

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->successResponse(
                $this->resolveResource(MenuTrashedResource::collection($menus)),
                __('api/navigation.trashed_success'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $menus,
                fn (Menu $menu) => $this->resolveResource(new MenuTrashedResource($menu)),
                $this->trashedRowActions,
                $actor,
            ),
        );
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
            __('api/navigation.restored'),
        );
    }

    private function optionalActor(): ?User
    {
        $user = Auth::guard('api')->user();

        return $user instanceof User ? $user : null;
    }

    private function requiredActor(): User
    {
        $user = Auth::guard('api')->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
