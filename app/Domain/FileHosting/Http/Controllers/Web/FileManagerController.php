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

    public function index(Request $request): View|RedirectResponse
    {
        $query = array_filter([
            'folder_id' => $request->query('folder_id'),
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/browse', $query);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        $data = $response->json('data', []);

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
