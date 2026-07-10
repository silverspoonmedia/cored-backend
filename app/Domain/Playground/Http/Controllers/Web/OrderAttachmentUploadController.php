<?php

namespace App\Domain\Playground\Http\Controllers\Web;

use App\Http\Controllers\Web\Controller;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Support\Api\ApiClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies browser-direct attachment upload orchestration to the playground API.
 */
class OrderAttachmentUploadController extends Controller
{
    use ConsumesApi;

    protected function api(): ApiClient
    {
        return app(ApiClient::class)->withPrefix('api/playground');
    }

    public function store(Request $request): JsonResponse
    {
        $response = $this->api()->post('attachments/uploads', $request->only(['name', 'mime', 'size']));

        return $this->jsonProxy($response);
    }

    public function show(string $upload): JsonResponse
    {
        $response = $this->api()->get("attachments/uploads/{$upload}");

        return $this->jsonProxy($response);
    }

    public function signParts(Request $request, string $upload): JsonResponse
    {
        $response = $this->api()->post("attachments/uploads/{$upload}/parts", [
            'part_numbers' => $request->input('part_numbers', []),
        ]);

        return $this->jsonProxy($response);
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $response = $this->api()->post("attachments/uploads/{$upload}/complete", [
            'parts' => $request->input('parts'),
        ]);

        return $this->jsonProxy($response);
    }

    public function destroy(string $upload): JsonResponse
    {
        $response = $this->api()->delete("attachments/uploads/{$upload}");

        return $this->jsonProxy($response);
    }

    private function jsonProxy(Response $response): JsonResponse
    {
        $status = $response->status();

        if ($status < 400 && ! $response->successful()) {
            $status = 400;
        }

        return response()->json(
            $response->json() ?? ['message' => $this->apiMessage($response)],
            $status,
        );
    }
}
