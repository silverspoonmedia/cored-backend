<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Resources\HashBlocklistResource;
use App\Domain\FileHosting\Models\FileHostingHashBlocklist;
use App\Domain\FileHosting\Services\HashBlocklistService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HashBlocklistController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HashBlocklistService $blocklist,
    ) {}

    public function index(): JsonResponse
    {
        $entries = FileHostingHashBlocklist::query()
            ->with('creator')
            ->latest('created_at')
            ->get();

        return $this->successResponse(
            HashBlocklistResource::collection($entries),
            __('api/file-hosting.blocklist_loaded'),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->blocklist->block(
            $validated['sha256'],
            $validated['reason'] ?? null,
            $request->user()?->id,
        );

        $entry->load('creator');

        return $this->successResponse(
            new HashBlocklistResource($entry),
            __('api/file-hosting.blocklist_saved'),
            201,
        );
    }

    public function destroy(string $entry): JsonResponse
    {
        $record = FileHostingHashBlocklist::query()->find($entry);

        if ($record !== null) {
            $this->blocklist->unblock($record->sha256);
        }

        return $this->successResponse(null, __('api/file-hosting.blocklist_removed'));
    }
}
