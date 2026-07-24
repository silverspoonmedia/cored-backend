<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Domain\Vtual\Http\Requests\PlatformApiProbeRequest;
use App\Domain\Vtual\Services\PlatformApiProbeService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Browser probe for PlatformApiGateway. Root/Admin only (manage credential perm).
 * Prefer API for automation; this page is for manual smoke tests.
 */
class PlatformApiProbeController extends Controller
{
    public function __construct(
        private readonly PlatformApiProbeService $probe,
    ) {}

    public function show(Request $request): View
    {
        return $this->webView('vtual.admin.platform-api-probe', [
            'methods' => PlatformApiProbeService::METHODS,
            'resultJson' => null,
            'error' => null,
            'input' => [
                'platform' => old('platform', $request->query('platform', 'youtube')),
                'method' => old('method', $request->query('method', 'fetch_videos')),
                'ids' => old('ids', $request->query('ids', '')),
                'handle' => old('handle', $request->query('handle', '')),
                'parts' => old('parts', $request->query('parts', '')),
                'chunked' => old('chunked', $request->boolean('chunked')),
            ],
        ]);
    }

    public function run(PlatformApiProbeRequest $request): View|JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

        try {
            $result = $this->probe->run($payload);
        } catch (BusinessRuleException $e) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], $e->statusCode());
            }

            return $this->webView('vtual.admin.platform-api-probe', [
                'methods' => PlatformApiProbeService::METHODS,
                'resultJson' => null,
                'error' => $e->getMessage(),
                'input' => $this->formInput($request),
            ]);
        } catch (Throwable $e) {
            report($e);
            $message = __('api/vtual.integrations.probe_failed');
            if ($this->wantsJson($request)) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 500);
            }

            return $this->webView('vtual.admin.platform-api-probe', [
                'methods' => PlatformApiProbeService::METHODS,
                'resultJson' => null,
                'error' => $message,
                'input' => $this->formInput($request),
            ]);
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => 'success',
                'message' => __('api/vtual.integrations.probe_success'),
                'data' => $result,
            ]);
        }

        return $this->webView('vtual.admin.platform-api-probe', [
            'methods' => PlatformApiProbeService::METHODS,
            'resultJson' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'error' => null,
            'input' => $this->formInput($request),
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->wantsJson()
            || $request->boolean('json')
            || $request->query('format') === 'json';
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        $ids = $request->input('ids', []);
        if (is_array($ids)) {
            $ids = implode(', ', $ids);
        }

        $parts = $request->input('parts', []);
        if (is_array($parts)) {
            $parts = implode(',', $parts);
        }

        return [
            'platform' => (string) $request->input('platform', 'youtube'),
            'method' => (string) $request->input('method', 'fetch_videos'),
            'ids' => (string) $ids,
            'handle' => (string) $request->input('handle', ''),
            'parts' => (string) $parts,
            'chunked' => $request->boolean('chunked'),
        ];
    }
}
