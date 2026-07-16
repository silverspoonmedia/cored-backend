<?php

namespace App\Domain\FileHosting\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * File manager — pure consumer of `/api/v1/file-hosting/*`.
 */
class FileManagerController extends Controller
{
    use ConsumesApi;

    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        $query = array_filter([
            'folder_id' => $request->query('folder_id'),
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/browse', $query);

        if (! $response->successful()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $this->apiMessage($response)], $response->status());
            }

            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        $data = $response->json('data', []);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        $quota = $this->api()->get('file-hosting/quota', array_filter([
            'user_id' => $request->query('user_id'),
        ]));

        return $this->webView('file-hosting.index', [
            'browse' => $data,
            'quota' => $quota->successful() ? $quota->json('data', []) : null,
            'ownerUserId' => $request->query('user_id'),
            'currentFolderId' => $request->query('folder_id'),
        ]);
    }

    public function trash(Request $request): View|RedirectResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/trash', $query);

        if (! $response->successful()) {
            return redirect()->route('file-hosting.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('file-hosting.trash', [
            'trash' => $response->json('data', []),
            'ownerUserId' => $request->query('user_id'),
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('file-hosting/folders', $request->only(['name', 'parent_id', 'user_id']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('parent_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.folder_created')),
            __('api/file-hosting.folder_created'),
        );
    }

    public function destroyFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->delete('file-hosting/folders/'.$folder, $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('return_folder_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.folder_deleted')),
            __('api/file-hosting.folder_deleted'),
        );
    }

    public function destroyFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->delete('file-hosting/files/'.$file, $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('return_folder_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.file_deleted')),
            __('api/file-hosting.file_deleted'),
        );
    }

    public function show(Request $request, string $file): View|RedirectResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/files/'.$file, $query);

        if (! $response->successful()) {
            return redirect()
                ->route('file-hosting.index', $query)
                ->with('error', $this->apiMessage($response));
        }

        return $this->webView('file-hosting.show', [
            'file' => $response->json('data', []),
            'ownerUserId' => $request->query('user_id'),
        ]);
    }

    public function download(Request $request, string $file): RedirectResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/files/'.$file.'/download', $query);

        if (! $response->successful()) {
            return redirect()
                ->route('file-hosting.files.show', array_filter([
                    'file' => $file,
                    'user_id' => $request->query('user_id'),
                ]))
                ->with('error', $this->apiMessage($response));
        }

        $url = $response->json('data.url');

        if (! is_string($url) || $url === '') {
            return redirect()
                ->route('file-hosting.files.show', array_filter([
                    'file' => $file,
                    'user_id' => $request->query('user_id'),
                ]))
                ->with('error', __('api/file-hosting.download_unavailable'));
        }

        return redirect()->away($url);
    }

    public function renameFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['name', 'user_id']);
        $response = $this->api()->patch('file-hosting/files/'.$file.'/rename', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('return_folder_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.file_renamed')),
            __('api/file-hosting.file_renamed'),
        );
    }

    public function renameFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['name', 'user_id']);
        $response = $this->api()->patch('file-hosting/folders/'.$folder.'/rename', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('return_folder_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.folder_renamed')),
            __('api/file-hosting.folder_renamed'),
        );
    }

    public function setFileVisibility(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['visibility', 'user_id']);
        $response = $this->api()->patch('file-hosting/files/'.$file.'/visibility', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->back()->with('success', __('api/file-hosting.visibility_updated')),
            __('api/file-hosting.visibility_updated'),
        );
    }

    public function setFolderVisibility(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['visibility', 'user_id']);
        $response = $this->api()->patch('file-hosting/folders/'.$folder.'/visibility', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->back()->with('success', __('api/file-hosting.visibility_updated')),
            __('api/file-hosting.visibility_updated'),
        );
    }

    public function moveFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['parent_id', 'user_id']);
        $response = $this->api()->patch('file-hosting/folders/'.$folder.'/move', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('parent_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.folder_moved')),
            __('api/file-hosting.folder_moved'),
        );
    }

    public function moveFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $payload = $request->only(['folder_id', 'user_id']);
        $response = $this->api()->patch('file-hosting/files/'.$file.'/move', $payload);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('folder_id'),
                'user_id' => $request->input('user_id'),
            ]))->with('success', __('api/file-hosting.file_moved')),
            __('api/file-hosting.file_moved'),
        );
    }

    public function restoreFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->post('file-hosting/folders/'.$folder.'/restore', $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.trash', $query)->with('success', __('api/file-hosting.folder_restored')),
            __('api/file-hosting.folder_restored'),
        );
    }

    public function restoreFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->post('file-hosting/files/'.$file.'/restore', $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.trash', $query)->with('success', __('api/file-hosting.file_restored')),
            __('api/file-hosting.file_restored'),
        );
    }

    public function forceDestroyFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->delete('file-hosting/folders/'.$folder.'/force', $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.trash', $query)->with('success', __('api/file-hosting.folder_force_deleted')),
            __('api/file-hosting.folder_force_deleted'),
        );
    }

    public function forceDestroyFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $query = array_filter(['user_id' => $request->input('user_id')]);
        $response = $this->api()->delete('file-hosting/files/'.$file.'/force', $query);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.trash', $query)->with('success', __('api/file-hosting.file_force_deleted')),
            __('api/file-hosting.file_force_deleted'),
        );
    }

    public function search(Request $request): JsonResponse
    {
        $query = array_filter([
            'q' => $request->query('q'),
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/search', $query);

        return response()->json($response->json(), $response->status());
    }

    public function initUpload(Request $request): JsonResponse
    {
        $response = $this->api()->post('file-hosting/uploads', $request->only([
            'name', 'mime', 'size', 'folder_id', 'user_id',
        ]));

        return response()->json($response->json(), $response->status());
    }

    public function uploadStatus(string $upload): JsonResponse
    {
        $response = $this->api()->get('file-hosting/uploads/'.$upload);

        return response()->json($response->json(), $response->status());
    }

    public function signParts(Request $request, string $upload): JsonResponse
    {
        $response = $this->api()->post(
            'file-hosting/uploads/'.$upload.'/sign-parts',
            $request->only(['part_numbers']),
        );

        return response()->json($response->json(), $response->status());
    }

    public function completeUpload(Request $request, string $upload): JsonResponse
    {
        $response = $this->api()->post(
            'file-hosting/uploads/'.$upload.'/complete',
            $request->only(['parts']),
        );

        return response()->json($response->json(), $response->status());
    }

    public function abortUpload(string $upload): JsonResponse
    {
        $response = $this->api()->delete('file-hosting/uploads/'.$upload);

        return response()->json($response->json(), $response->status());
    }
}
