<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApprovalWorkflowAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['email' => 'workflow-admin@example.test']);
        $this->admin->assignRole('Admin');

        $this->user = User::factory()->create(['email' => 'workflow-user@example.test']);
        $this->user->assignRole('User');
    }

    public function test_admin_can_create_workflow_through_api(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/v1/admin/approval-workflows', [
            'document_type' => 'purchase_order',
            'name' => 'Purchase Order Approval',
            'description' => 'Standard PO approval workflow',
            'version' => 1,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'PURCHASE_ORDER')
            ->assertJsonPath('data.name', 'Purchase Order Approval');

        $this->assertDatabaseHas('approval_workflows', [
            'document_type' => 'PURCHASE_ORDER',
            'name' => 'Purchase Order Approval',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_add_step_and_approver_through_api(): void
    {
        $workflow = $this->createWorkflow();

        $stepResponse = $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/approval-workflows/{$workflow->id}/steps", [
            'step_order' => 1,
            'step_name' => 'Manager Review',
            'condition_field' => 'grand_total',
            'condition_op' => '>=',
            'condition_value' => '10000',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'BACK_TO_STEP',
            'reject_target_step_order' => 1,
            'reject_reset_downstream' => true,
            'approve_set_doc_status' => 'APPROVED',
            'reject_set_doc_status' => 'REJECTED',
            'reminder_after_days' => 2,
            'escalate_after_days' => 5,
            'escalate_to_role' => 'Admin',
            'allow_reassignment' => true,
        ]);

        $stepResponse->assertCreated()
            ->assertJsonPath('data.step_order', 1)
            ->assertJsonPath('data.condition.field', 'grand_total')
            ->assertJsonPath('data.allow_reassignment', true);

        $stepId = $stepResponse->json('data.id');

        $approverResponse = $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/approval-workflows/{$workflow->id}/steps/{$stepId}/approvers", [
            'required_role' => 'Management',
            'slot_label' => 'Department Manager',
        ]);

        $approverResponse->assertCreated()
            ->assertJsonPath('data.required_role', 'Management')
            ->assertJsonPath('data.slot_label', 'Department Manager');

        $this->assertDatabaseHas('approval_workflow_steps', [
            'id' => $stepId,
            'workflow_id' => $workflow->id,
            'step_name' => 'Manager Review',
            'parallel_mode' => 'UNANIMOUS',
        ]);

        $this->assertDatabaseHas('approval_workflow_step_approvers', [
            'workflow_step_id' => $stepId,
            'required_role' => 'Management',
            'slot_label' => 'Department Manager',
        ]);
    }

    public function test_admin_can_activate_and_deactivate_workflow_through_api(): void
    {
        $workflow = $this->createWorkflow(['document_type' => 'ORDER', 'is_active' => false]);

        $activateResponse = $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/approval-workflows/{$workflow->id}/activate");
        $activateResponse->assertOk();
        $this->assertTrue($workflow->fresh()->is_active);

        $deactivateResponse = $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/approval-workflows/{$workflow->id}/deactivate");
        $deactivateResponse->assertOk();
        $this->assertFalse($workflow->fresh()->is_active);
    }

    public function test_non_admin_cannot_manage_workflows(): void
    {
        $workflow = $this->createWorkflow();

        $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/admin/approval-workflows')
            ->assertForbidden();

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/admin/approval-workflows', [
                'document_type' => 'PO',
                'name' => 'Blocked',
            ])
            ->assertForbidden();

        $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/admin/approval-workflows/{$workflow->id}")
            ->assertForbidden();
    }

    public function test_admin_menu_seeder_creates_approval_matrix_entry(): void
    {
        $this->seed(MenuSeeder::class);

        $this->assertDatabaseHas('menus', [
            'route' => 'admin.approval-workflows.index',
            'icon' => 'bi bi-diagram-3',
            'type' => 'p',
        ]);

        $menu = Menu::query()->where('route', 'admin.approval-workflows.index')->firstOrFail();
        $roleNames = $menu->roles()->pluck('name')->all();

        $this->assertContains('Root', $roleNames);
        $this->assertContains('Admin', $roleNames);
        $this->assertNotContains('Moderator', $roleNames);
        $this->assertNotContains('Management', $roleNames);
    }

    public function test_web_admin_routes_gated_by_root_or_admin_role(): void
    {
        $route = Route::getRoutes()->getByName('admin.approval-workflows.index');

        $this->assertNotNull($route);
        $this->assertContains('role:Root|Admin', $route->gatherMiddleware());
    }

    public function test_menu_flush_cache_command_clears_navigation_snapshot(): void
    {
        Cache::put('menus.navigation.snapshot', ['dummy'], now()->addHour());

        $this->artisan('menu:flush-cache')->assertSuccessful();

        $this->assertNull(Cache::get('menus.navigation.snapshot'));
    }

    private function createWorkflow(array $attributes = []): ApprovalWorkflow
    {
        return ApprovalWorkflow::create([
            'document_type' => $attributes['document_type'] ?? 'ORDER',
            'name' => $attributes['name'] ?? 'Order Approval',
            'description' => $attributes['description'] ?? 'Approval matrix for orders',
            'version' => $attributes['version'] ?? 1,
            'is_active' => $attributes['is_active'] ?? true,
            'created_by' => $this->admin->id,
        ]);
    }
}
