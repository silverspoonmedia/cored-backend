<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\StoreUserRequest;
use App\Http\Requests\Playground\UpdateUserRequest;
use App\Services\Playground\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function ping(): JsonResponse
    {
        return $this->successResponse(null, 'Pong Global', 200);
    }

    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->userService->list(),
            'Users retrieved successfully.',
        );
    }

    public function show(string $user): JsonResponse
    {
        return $this->successResponse(
            $this->userService->get($user),
            'User retrieved successfully.',
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $record = $this->userService->create($request->validated());

        return $this->successResponse($record, 'User created successfully.', 201);
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $record = $this->userService->update($user, $request->validated());

        return $this->successResponse($record, 'User updated successfully.');
    }

    public function destroy(string $user): JsonResponse
    {
        $this->userService->delete($user);

        return $this->successResponse(null, 'User deleted successfully.');
    }
}
