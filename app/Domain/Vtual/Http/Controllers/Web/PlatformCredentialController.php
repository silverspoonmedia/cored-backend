<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin platform credentials — pure consumer of `/api/v1/vtual/admin/*`.
 */
class PlatformCredentialController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $platforms = $this->api()->get('vtual/platforms');

        if (! $platforms->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($platforms));
        }

        return $this->webView('vtual.admin.credentials.index', [
            'platforms' => collect($platforms->json('data', [])),
        ]);
    }

    public function showPlatform(string $platform): View|RedirectResponse
    {
        $platforms = $this->api()->get('vtual/platforms');
        $credentials = $this->api()->get('vtual/admin/platforms/'.$platform.'/credentials');

        if (! $credentials->successful()) {
            return redirect()->route('vtual.admin.credentials.index')
                ->with('error', $this->apiMessage($credentials));
        }

        $platformRow = collect($platforms->successful() ? $platforms->json('data', []) : [])
            ->firstWhere('id', $platform);

        return $this->webView('vtual.admin.credentials.platform', [
            'platformId' => $platform,
            'platform' => $platformRow ?? ['id' => $platform, 'name' => $platform, 'key' => ''],
            'credentials' => collect($credentials->json('data', [])),
        ]);
    }

    public function store(Request $request, string $platform): RedirectResponse|JsonResponse
    {
        $type = (string) $request->input('type', '');
        $secret = $this->secretFromRequest($request, $type);

        $payload = [
            'label' => $request->input('label'),
            'type' => $type,
            'priority' => $request->input('priority'),
            // Service always forces pending until Test; do not send active from UI.
            'secret' => $secret,
        ];

        $response = $this->api()->post('vtual/admin/platforms/'.$platform.'/credentials', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.admin.credentials.platform', $platform)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.credential_saved'))),
            __('web/vtual.ajax.credential_saved'),
        );
    }

    public function update(Request $request, string $credential): RedirectResponse|JsonResponse
    {
        $platformId = (string) $request->input('platform_id');
        $type = (string) $request->input('type', '');

        $payload = [
            'label' => $request->input('label'),
            'priority' => $request->input('priority'),
            'status' => $request->input('status'),
        ];

        $secret = $this->secretFromRequest($request, $type, allowEmpty: true);
        if ($secret !== []) {
            $payload['secret'] = $secret;
        }

        $response = $this->api()->patch('vtual/admin/credentials/'.$credential, $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn () => $this->platformRedirect($platformId)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.credential_saved'))),
            __('web/vtual.ajax.credential_saved'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $credential): RedirectResponse|JsonResponse
    {
        $platformId = (string) $request->input('platform_id');
        $response = $this->api()->delete('vtual/admin/credentials/'.$credential);

        return $this->respondFromApi(
            $request,
            $response,
            fn () => $this->platformRedirect($platformId)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.credential_deleted'))),
            __('web/vtual.ajax.credential_deleted'),
        );
    }

    public function disable(Request $request, string $credential): RedirectResponse|JsonResponse
    {
        $platformId = (string) $request->input('platform_id');
        $response = $this->api()->post('vtual/admin/credentials/'.$credential.'/disable');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => $this->platformRedirect($platformId)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.credential_disabled'))),
            __('web/vtual.ajax.credential_disabled'),
            stayOnPageOnSuccess: true,
        );
    }

    public function test(Request $request, string $credential): RedirectResponse|JsonResponse
    {
        $platformId = (string) $request->input('platform_id');
        $response = $this->api()->post('vtual/admin/credentials/'.$credential.'/test');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => $this->platformRedirect($platformId)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.credential_tested'))),
            __('web/vtual.ajax.credential_tested'),
            stayOnPageOnSuccess: true,
        );
    }

    private function platformRedirect(string $platformId): RedirectResponse
    {
        return $platformId !== ''
            ? redirect()->route('vtual.admin.credentials.platform', $platformId)
            : redirect()->route('vtual.admin.credentials.index');
    }

    /**
     * @return array<string, string>
     */
    private function secretFromRequest(Request $request, string $type, bool $allowEmpty = false): array
    {
        $map = match ($type) {
            'api_key' => ['api_key'],
            'oauth_app' => ['client_id', 'client_secret'],
            'bearer' => ['access_token', 'refresh_token', 'expires_at'],
            default => [],
        };

        $secret = [];
        foreach ($map as $key) {
            $value = $request->input($key);
            if ($value === null) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            $secret[$key] = $trimmed;
        }

        if ($allowEmpty) {
            return $secret;
        }

        return $secret;
    }
}
