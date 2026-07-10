<?php

namespace Tests\Domain\Approval\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WebApprovalWorkflowRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_non_admin_user_is_denied_approval_workflow_admin_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingWithJwtCookie($user)
            ->get(route('admin.approval-workflows.index'))
            ->assertForbidden();
    }

    public function test_admin_user_can_access_approval_workflow_admin_index(): void
    {
        Http::fake([
            '*/api/v1/admin/approval-workflows' => Http::response([
                'status' => 'success',
                'message' => 'Workflows retrieved.',
                'data' => [],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingWithJwtCookie($admin)
            ->get(route('admin.approval-workflows.index'))
            ->assertOk();
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
