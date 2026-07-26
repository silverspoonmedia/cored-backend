<?php

namespace App\Domain\FileHosting\Http\Controllers\Web;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileHostingHashBlocklist;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\HashBlocklistService;
use App\Domain\Microstock\Services\CollectionService;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
            'workspace_id' => $request->query('workspace_id'),
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
        $quotaData = $quota->successful() ? $quota->json('data', []) : null;

        $workspaces = $this->api()->get('file-hosting/workspaces');
        $workspaceList = $workspaces->successful() ? ($workspaces->json('data') ?? []) : [];
        if (! is_array($workspaceList)) {
            $workspaceList = [];
        }

        $activity = [];
        $members = [];
        $workspaceId = $request->query('workspace_id');
        $currentWorkspace = null;
        if ($workspaceId) {
            $act = $this->api()->get('file-hosting/workspaces/'.$workspaceId.'/activity', ['limit' => 30]);
            $activity = $act->successful() ? ($act->json('data') ?? []) : [];

            $mem = $this->api()->get('file-hosting/workspaces/'.$workspaceId.'/members');
            $members = $mem->successful() ? ($mem->json('data') ?? []) : [];

            $show = $this->api()->get('file-hosting/workspaces/'.$workspaceId);
            $currentWorkspace = $show->successful() ? $show->json('data') : null;
        }

        return $this->webView('file-hosting.index', [
            'browse' => $data,
            'quota' => $quotaData,
            'ownerUserId' => $request->query('user_id'),
            'currentFolderId' => $request->query('folder_id'),
            'workspaceId' => $workspaceId,
            'workspaces' => $workspaceList,
            'workspaceActivity' => $activity,
            'workspaceMembers' => is_array($members) ? $members : [],
            'currentWorkspace' => $currentWorkspace,
            'planState' => is_array($quotaData) ? ($quotaData['state'] ?? 'free') : 'free',
            'canCreateWorkspace' => is_array($quotaData) && ($quotaData['can_create_workspace'] ?? false) === true,
            'collaborativeWorks' => is_array($quotaData) && ($quotaData['collaborative_works'] ?? false) === true,
            'ownedWorkspaceUsed' => is_array($quotaData) ? (int) ($quotaData['owned_workspace_used'] ?? 0) : 0,
            'ownedWorkspaceLimit' => is_array($quotaData) ? (int) ($quotaData['owned_workspace_limit'] ?? 0) : 0,
        ]);
    }

    public function storeWorkspace(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('file-hosting/workspaces', $request->only(['name']));

        $workspaceId = $response->successful()
            ? ($response->json('data.id') ?? null)
            : null;

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'workspace_id' => $workspaceId,
            ]))->with('success', __('api/file-hosting.workspace.created')),
            __('api/file-hosting.workspace.created'),
        );
    }

    public function inviteWorkspaceMember(Request $request, string $workspace): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post(
            'file-hosting/workspaces/'.$workspace.'/invites',
            $request->only(['email', 'role']),
        );

        $token = $response->successful() ? ($response->json('data.token') ?? null) : null;
        $email = $response->successful() ? ($response->json('data.email') ?? $request->input('email')) : null;

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', [
                'workspace_id' => $workspace,
            ])->with('success', __('api/file-hosting.workspace.invite_sent'))
                ->with('workspace_invite_token', $token)
                ->with('workspace_invite_email', $email),
            __('api/file-hosting.workspace.invite_sent'),
        );
    }

    public function acceptWorkspaceInvite(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post(
            'file-hosting/workspaces/invites/accept',
            $request->only(['token']),
        );

        $workspaceId = $response->successful()
            ? ($response->json('data.workspace_id') ?? null)
            : null;

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'workspace_id' => $workspaceId,
            ]))->with('success', __('api/file-hosting.workspace.invite_accepted')),
            __('api/file-hosting.workspace.invite_accepted'),
        );
    }

    public function removeWorkspaceMember(Request $request, string $workspace, string $user): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete('file-hosting/workspaces/'.$workspace.'/members/'.$user);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', [
                'workspace_id' => $workspace,
            ])->with('success', __('api/file-hosting.workspace.member_removed')),
            __('api/file-hosting.workspace.member_removed'),
        );
    }

    public function archiveWorkspace(Request $request, string $workspace): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('file-hosting/workspaces/'.$workspace.'/archive');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index')
                ->with('success', __('api/file-hosting.workspace.archived_success')),
            __('api/file-hosting.workspace.archived_success'),
        );
    }

    public function trash(Request $request): View|RedirectResponse|JsonResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
            'workspace_id' => $request->query('workspace_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/trash', $query);

        if (! $response->successful()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $this->apiMessage($response)], $response->status());
            }

            return redirect()->route('file-hosting.index')->with('error', $this->apiMessage($response));
        }

        $data = $response->json('data', []);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return $this->webView('file-hosting.trash', [
            'trash' => $data,
            'ownerUserId' => $request->query('user_id'),
            'workspaceId' => $request->query('workspace_id'),
        ]);
    }

    public function quota(Request $request): JsonResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/quota', $query);

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json(['data' => $response->json('data', [])]);
    }

    public function workspaceMembers(Request $request, string $workspace): JsonResponse
    {
        $response = $this->api()->get('file-hosting/workspaces/'.$workspace.'/members');

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json(['data' => $response->json('data', [])]);
    }

    public function workspaceActivity(Request $request, string $workspace): JsonResponse
    {
        $response = $this->api()->get('file-hosting/workspaces/'.$workspace.'/activity', [
            'limit' => (int) $request->query('limit', 30),
        ]);

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json(['data' => $response->json('data', [])]);
    }

    public function storeFolder(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('file-hosting/folders', $request->only(['name', 'parent_id', 'user_id', 'workspace_id']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('file-hosting.index', array_filter([
                'folder_id' => $request->input('parent_id'),
                'user_id' => $request->input('user_id'),
                'workspace_id' => $request->input('workspace_id'),
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

    public function show(Request $request, string $file): View|RedirectResponse|JsonResponse
    {
        $query = array_filter([
            'user_id' => $request->query('user_id'),
            'workspace_id' => $request->query('workspace_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('file-hosting/files/'.$file, $query);

        if (! $response->successful()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $this->apiMessage($response)], $response->status());
            }

            return redirect()
                ->route('file-hosting.index', $query)
                ->with('error', $this->apiMessage($response));
        }

        $data = $response->json('data', []);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return $this->webView('file-hosting.show', [
            'file' => $data,
            'ownerUserId' => $request->query('user_id'),
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        $response = $this->api()->get('file-hosting/workspaces');

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        $list = $response->json('data') ?? [];

        return response()->json([
            'data' => is_array($list) ? $list : [],
        ]);
    }

    public function microstockSlots(Request $request): JsonResponse
    {
        $response = $this->api()->get('microstock/slots');

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json([
            'data' => $response->json('data') ?? [],
        ]);
    }

    public function storeMicrostockWorkspace(Request $request): JsonResponse
    {
        $response = $this->api()->post('microstock/workspaces', $request->only(['name']));

        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json([
            'message' => $response->json('message'),
            'data' => $response->json('data') ?? [],
        ], $response->status());
    }

    public function lockMicrostockCollection(Request $request, string $folder): JsonResponse
    {
        $response = $this->api()->post('microstock/collections/'.$folder.'/lock', $request->only(['hint']));

        return $this->microstockProxy($response);
    }

    public function analyzeMicrostockCollection(Request $request, string $folder): JsonResponse
    {
        $response = $this->api()->post('microstock/collections/'.$folder.'/analyze');

        return $this->microstockProxy($response);
    }

    public function microstockCollectionStatus(Request $request, string $folder): JsonResponse
    {
        $response = $this->api()->get('microstock/collections/'.$folder.'/status');

        return $this->microstockProxy($response);
    }

    public function exportMicrostockCollection(Request $request, string $folder): JsonResponse
    {
        $response = $this->api()->post('microstock/collections/'.$folder.'/export');

        return $this->microstockProxy($response);
    }

    public function downloadMicrostockCollectionCsv(Request $request, string $folder, CollectionService $collections): Response
    {
        $csv = $collections->downloadCsv($request->user(), $folder);

        return response($csv['contents'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['name'].'"',
        ]);
    }

    private function microstockProxy(\Illuminate\Http\Client\Response $response): JsonResponse
    {
        if (! $response->successful()) {
            return response()->json(['message' => $this->apiMessage($response)], $response->status());
        }

        return response()->json([
            'message' => $response->json('message'),
            'data' => $response->json('data') ?? [],
        ], $response->status());
    }

    public function download(Request $request, string $file, FileService $files): RedirectResponse
    {
        try {
            $payload = $files->download(
                $request->user(),
                $file,
                $request->query('user_id'),
                $request->query('workspace_id'),
            );
        } catch (FileHostingException $e) {
            return redirect()
                ->route('file-hosting.files.show', array_filter([
                    'file' => $file,
                    'user_id' => $request->query('user_id'),
                ], fn ($v) => $v !== null && $v !== ''))
                ->with('error', $e->getMessage());
        }

        $url = $payload['url'] ?? '';
        if (! is_string($url) || $url === '') {
            return redirect()
                ->route('file-hosting.files.show', array_filter([
                    'file' => $file,
                    'user_id' => $request->query('user_id'),
                ], fn ($v) => $v !== null && $v !== ''))
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
            'name', 'mime', 'size', 'folder_id', 'user_id', 'workspace_id', 'disk',
        ]));

        return response()->json($response->json(), $response->status());
    }

    public function bulkFileVisibility(Request $request): JsonResponse
    {
        $response = $this->api()->post('file-hosting/files/bulk-visibility', $request->only(['ids', 'visibility', 'user_id']));

        return response()->json($response->json(), $response->status());
    }

    public function bulkFileDestroy(Request $request): JsonResponse
    {
        $response = $this->api()->delete('file-hosting/files/bulk', $request->only(['ids', 'user_id']));

        return response()->json($response->json(), $response->status());
    }

    public function bulkFolderVisibility(Request $request): JsonResponse
    {
        $response = $this->api()->post('file-hosting/folders/bulk-visibility', $request->only(['ids', 'visibility', 'user_id']));

        return response()->json($response->json(), $response->status());
    }

    public function bulkFolderDestroy(Request $request): JsonResponse
    {
        $response = $this->api()->delete('file-hosting/folders/bulk', $request->only(['ids', 'user_id']));

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

    /**
     * DMCA hash blocklist admin page (list + add + remove).
     */
    public function blocklist(): View
    {
        $entries = FileHostingHashBlocklist::query()
            ->latest('created_at')
            ->get();

        return $this->webView('file-hosting.blocklist', [
            'entries' => $entries,
        ]);
    }

    public function storeBlocklistEntry(Request $request, HashBlocklistService $blocklist): RedirectResponse
    {
        $validated = $request->validate([
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $blocklist->block($validated['sha256'], $validated['reason'] ?? null, $request->user()?->id);

        return redirect()->route('file-hosting.blocklist.index')
            ->with('success', __('api/file-hosting.blocklist_saved'));
    }

    public function destroyBlocklistEntry(string $entry, HashBlocklistService $blocklist): RedirectResponse
    {
        $record = FileHostingHashBlocklist::query()->find($entry);

        if ($record !== null) {
            $blocklist->unblock($record->sha256);
        }

        return redirect()->route('file-hosting.blocklist.index')
            ->with('success', __('api/file-hosting.blocklist_removed'));
    }
}
