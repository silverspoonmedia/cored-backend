<?php

namespace App\Domain\Ai\Http\Controllers\API\V1;

use App\Domain\Ai\Http\Requests\StoreAiConnectionRequest;
use App\Domain\Ai\Http\Requests\UpdateAiConnectionRequest;
use App\Domain\Ai\Http\Resources\AiConnectionResource;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionService;
use App\Domain\Ai\Support\ListActions\AiConnectionQueryRowActions;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConnectionController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly AiConnectionService $connections,
        private readonly AiConnectionQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiConnection::class);

        $list = $this->connections->list();

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(AiConnectionResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/ai.connection.list_success'),
        );
    }

    public function store(StoreAiConnectionRequest $request): JsonResponse
    {
        $this->authorize('create', AiConnection::class);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $connection = $this->connections->create($actor, $request->connectionPayload());

        return $this->successResponse(
            $this->present($connection, $actor),
            __('api/ai.connection.created'),
            201,
        );
    }

    public function show(Request $request, AiConnection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        return $this->successResponse(
            $this->present($this->connections->findOrFail((string) $connection->id), $request->user()),
            __('api/ai.connection.show_success'),
        );
    }

    public function update(UpdateAiConnectionRequest $request, AiConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $updated = $this->connections->update($actor, $connection, $request->connectionPayload());

        return $this->successResponse(
            $this->present($updated, $actor),
            __('api/ai.connection.updated'),
        );
    }

    public function destroy(Request $request, AiConnection $connection): JsonResponse
    {
        $this->authorize('delete', $connection);

        $this->connections->delete($connection);

        return $this->successResponse(null, __('api/ai.connection.deleted'));
    }

    public function setDefault(Request $request, AiConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $updated = $this->connections->setDefault($connection);

        return $this->successResponse(
            $this->present($updated, $request->user()),
            __('api/ai.connection.default_updated'),
        );
    }

    public function test(Request $request, AiConnection $connection): JsonResponse
    {
        $this->authorize('update', $connection);

        $result = $this->connections->test($connection);
        $loaded = $result->connection ?? $this->connections->findOrFail((string) $connection->id);

        $payload = [
            'connection' => $this->present($loaded, $request->user()),
            'test' => $result->toPublicArray(),
        ];

        if (! $result->ok) {
            return $this->errorResponse($result->message, 422, $payload);
        }

        return $this->successResponse($payload, $result->message);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AiConnection $connection, mixed $actor): array
    {
        return QueryRowActionAttacher::attach(
            [$this->resolveResource(new AiConnectionResource($connection))],
            [$connection],
            $this->queryRowActions,
            $actor instanceof User ? $actor : null,
        )[0];
    }
}
