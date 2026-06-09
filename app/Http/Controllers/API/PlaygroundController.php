<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\IndexUserRequest;
use App\Http\Requests\Playground\StoreUserRequest;
use App\Http\Requests\Playground\UpdateUserRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Http\Resources\Playground\PlaygroundUserResource;
use App\Models\User;
use App\Services\Playground\UserService;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\DataTable\Resolvers\PlaygroundUserRowActionResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly UserService $userService,
        private readonly DataTableResponseFactory $dataTables,
        private readonly PlaygroundUserRowActionResolver $rowActions,
    ) {}

    public function ping(): JsonResponse
    {
        return $this->successResponse(null, 'Pong Global', 200);
    }

    public function index(IndexUserRequest $request): JsonResponse
    {
        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->successResponse(
                $this->resolveResource(PlaygroundUserResource::collection($this->userService->list())),
                'Users retrieved successfully.',
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $this->userService->list(),
                fn (User $user) => $this->resolveResource(new PlaygroundUserResource($user)),
                $this->rowActions,
            ),
        );
    }

    public function show(string $user): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new PlaygroundUserResource($this->userService->get($user))),
            'User retrieved successfully.',
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $record = $this->userService->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new PlaygroundUserResource($record)),
            'User created successfully.',
            201,
        );
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $record = $this->userService->update($user, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PlaygroundUserResource($record)),
            'User updated successfully.',
        );
    }

    public function destroy(string $user): JsonResponse
    {
        $this->userService->delete($user);

        return $this->successResponse(null, 'User deleted successfully.');
    }
}
