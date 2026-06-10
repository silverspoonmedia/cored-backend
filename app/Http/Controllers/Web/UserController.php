<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web user management — a pure consumer of the versioned API.
 *
 * No validation, CRUD or RBAC lives here: every action forwards to
 * `/api/v1/users` (see {@see \App\Http\Controllers\API\V1\UserController}) via
 * {@see ConsumesApi}, and the controller only turns the JSON envelope into a
 * Blade view or a redirect. Authorization is enforced entirely by the API.
 */
class UserController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return view('users.index');
    }

    /**
     * Server-side DataTables feed: proxy the API's datatable payload, then map
     * each row's allowed actions (decided by the API's RBAC) onto web routes the
     * browser can navigate/submit. On any API failure, render an empty table.
     */
    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('users', array_merge(
            $request->query(),
            ['display_mode' => 'datatable'],
        ));

        if (! $response->successful()) {
            return response()->json([
                'draw' => (int) $request->query('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $payload = $response->json();
        $payload['data'] = array_map(
            fn (array $row): array => $this->mapRowActions($row),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    public function create(): View|RedirectResponse
    {
        $response = $this->api()->get('users/assignable-roles');

        if (! $response->successful()) {
            return redirect()->route('users.index')->with('error', $this->apiMessage($response));
        }

        return view('users.create', [
            'assignableRoles' => collect($response->json('data', [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $response = $this->api()->post('users', $this->payload($request));

        return $this->redirectFromApi(
            $response,
            fn (): RedirectResponse => redirect()->route('users.index')->with('success', 'Pengguna berhasil dibuat.'),
        );
    }

    public function edit(string $user): View|RedirectResponse
    {
        $response = $this->api()->get("users/{$user}");

        if (! $response->successful()) {
            return redirect()->route('users.index')->with('error', $this->apiMessage($response));
        }

        $roles = $this->api()->get('users/assignable-roles');

        $data = $response->json('data', []);

        return view('users.edit', [
            'user' => (object) $data,
            'currentRole' => data_get($data, 'roles.0.name'),
            'assignableRoles' => collect($roles->successful() ? $roles->json('data', []) : []),
        ]);
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $response = $this->api()->put("users/{$user}", $this->payload($request));

        return $this->redirectFromApi(
            $response,
            fn (): RedirectResponse => redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.'),
        );
    }

    public function destroy(string $user): RedirectResponse
    {
        $response = $this->api()->delete("users/{$user}");

        return $this->redirectFromApi(
            $response,
            fn (): RedirectResponse => redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.'),
        );
    }

    /**
     * Form fields to forward to the API (CSRF/method spoofing stripped).
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $request->except(['_token', '_method']);
    }

    /**
     * Rewrite the API's allowed-action keys onto clickable web routes. The API
     * decides WHICH actions the actor may take (RBAC); the web layer only maps
     * those keys to its own edit page / delete form.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRowActions(array $row): array
    {
        $allowed = array_column($row['actions'] ?? [], 'key');
        $id = $row['id'] ?? null;
        $actions = [];

        if ($id !== null && in_array('edit', $allowed, true)) {
            $actions[] = [
                'key' => 'edit',
                'label' => 'Edit',
                'method' => 'GET',
                'url' => route('users.edit', $id),
                'style' => 'info',
                'icon' => 'bi bi-pencil',
            ];
        }

        if ($id !== null && in_array('delete', $allowed, true)) {
            $actions[] = [
                'key' => 'delete',
                'label' => 'Hapus',
                'method' => 'DELETE',
                'url' => route('users.destroy', $id),
                'style' => 'danger',
                'icon' => 'bi bi-trash',
                'confirm' => 'Yakin ingin menghapus pengguna ini?',
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
