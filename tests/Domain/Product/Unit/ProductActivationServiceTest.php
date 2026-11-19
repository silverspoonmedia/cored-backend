<?php

namespace Tests\Domain\Product\Unit;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Enums\ProductActivationType;
use App\Support\Product\Exceptions\ProductActivationException;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductActivationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = app(ProductActivationService::class);
    }

    public function test_activate_implicit_creates_active_row(): void
    {
        Event::fake();
        $user = $this->userWithRole('User');

        $activation = $this->service->activateImplicit($user, 'Archivd');

        $this->assertSame(ProductActivationState::Active, $activation->state);
        $this->assertSame(ProductActivationType::Implicit, $activation->activation_type);
        $this->assertNotNull($activation->activated_at);
        $this->assertSame('register_hook', $activation->source);
        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Archivd',
            'state' => ProductActivationState::Active->value,
        ]);
    }

    public function test_activate_implicit_is_idempotent(): void
    {
        $user = $this->userWithRole('User');

        $first = $this->service->activateImplicit($user, 'Archivd');
        $second = $this->service->activateImplicit($user, 'Archivd');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(ProductActivationState::Active, $second->state);
        $this->assertCount(1, ProductActivation::where('user_id', $user->id)->get());
    }

    public function test_activate_implicit_reactivates_suspended(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation, 'test');

        $reactivated = $this->service->activateImplicit($user, 'Archivd');

        $this->assertSame(ProductActivationState::Active, $reactivated->state);
    }

    public function test_request_explicit_creates_pending(): void
    {
        $user = $this->userWithRole('User');

        config()->set('products.Billing', [
            'activation_type' => 'explicit',
            'fulfillment_target' => null,
        ]);

        $activation = $this->service->requestExplicit($user, 'Billing', ['company' => 'Acme']);

        $this->assertSame(ProductActivationState::Pending, $activation->state);
        $this->assertSame(ProductActivationType::Explicit, $activation->activation_type);
        $this->assertSame(['company' => 'Acme'], $activation->provisioning_payload);
    }

    public function test_approve_transitions_pending_to_active(): void
    {
        $user = $this->userWithRole('User');

        config()->set('products.Billing', [
            'activation_type' => 'explicit',
            'fulfillment_target' => null,
        ]);

        $activation = $this->service->requestExplicit($user, 'Billing');
        $approved = $this->service->approve($activation, ['approved_by' => 'admin'], 'admin');

        $this->assertSame(ProductActivationState::Active, $approved->state);
        $this->assertNotNull($approved->activated_at);
    }

    public function test_reject_transitions_pending_to_expired(): void
    {
        $user = $this->userWithRole('User');

        config()->set('products.Billing', [
            'activation_type' => 'explicit',
            'fulfillment_target' => null,
        ]);

        $activation = $this->service->requestExplicit($user, 'Billing');
        $rejected = $this->service->reject($activation, 'invalid docs');

        $this->assertSame(ProductActivationState::Expired, $rejected->state);
        $this->assertNotNull($rejected->expired_at);
        $this->assertSame('invalid docs', $rejected->provisioning_payload['rejection_reason']);
    }

    public function test_suspend_blocks_active(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');

        $suspended = $this->service->suspend($activation, 'abuse');

        $this->assertSame(ProductActivationState::Suspended, $suspended->state);
        $this->assertNotNull($suspended->suspended_at);
        $this->assertSame('abuse', $suspended->provisioning_payload['suspension_reason']);
    }

    public function test_suspend_rejects_already_suspended(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation);

        $this->expectException(ProductActivationException::class);
        $this->service->suspend($activation);
    }

    public function test_reactivate_transitions_suspended_to_active(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation);

        $reactivated = $this->service->reactivate($activation);

        $this->assertSame(ProductActivationState::Active, $reactivated->state);
        $this->assertNull($reactivated->suspended_at);
    }

    public function test_mark_expired_handles_active(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');

        $expired = $this->service->markExpired($activation);

        $this->assertSame(ProductActivationState::Expired, $expired->state);
        $this->assertNotNull($expired->expired_at);
    }

    public function test_mark_expired_idempotent_on_expired(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->markExpired($activation);

        $result = $this->service->markExpired($activation);

        $this->assertSame($activation->id, $result->id);
        $this->assertSame(ProductActivationState::Expired, $result->state);
    }

    public function test_is_active_returns_true_only_for_active(): void
    {
        $user = $this->userWithRole('User');

        $this->assertFalse($this->service->isActive($user->id, 'Archivd'));

        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->assertTrue($this->service->isActive($user->id, 'Archivd'));

        $this->service->suspend($activation);
        $this->assertFalse($this->service->isActive($user->id, 'Archivd'));
    }

    public function test_find_or_fail_throws_when_missing(): void
    {
        $user = $this->userWithRole('User');

        $this->expectException(ProductNotFoundException::class);
        $this->service->findOrFail($user->id, 'Archivd');
    }

    public function test_unknown_product_key_throws(): void
    {
        $user = $this->userWithRole('User');

        $this->expectException(ProductNotFoundException::class);
        $this->service->activateImplicit($user, 'Nonexistent');
    }

    public function test_type_mismatch_throws(): void
    {
        $user = $this->userWithRole('User');

        $this->expectException(ProductActivationException::class);
        $this->service->requestExplicit($user, 'Archivd');
    }

    public function test_for_user_returns_all_user_activations(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'Archivd');

        config()->set('products.Billing', [
            'activation_type' => 'explicit',
            'fulfillment_target' => null,
        ]);
        $this->service->requestExplicit($user, 'Billing');

        $activations = $this->service->forUser($user);

        $this->assertCount(2, $activations);
        $this->assertTrue($activations->contains('product_key', 'Archivd'));
        $this->assertTrue($activations->contains('product_key', 'Billing'));
    }

    public function test_for_product_filters_by_state(): void
    {
        $user1 = $this->userWithRole('User');
        $user2 = $this->userWithRole('User', 'second@example.com');

        $this->service->activateImplicit($user1, 'Archivd');
        $active2 = $this->service->activateImplicit($user2, 'Archivd');
        $this->service->suspend($active2);

        $active = $this->service->forProduct('Archivd', ProductActivationState::Active);
        $suspended = $this->service->forProduct('Archivd', ProductActivationState::Suspended);

        $this->assertCount(1, $active);
        $this->assertCount(1, $suspended);
    }

    public function test_count_by_product_state(): void
    {
        $user1 = $this->userWithRole('User');
        $user2 = $this->userWithRole('User', 'second@example.com');

        $this->service->activateImplicit($user1, 'Archivd');
        $active2 = $this->service->activateImplicit($user2, 'Archivd');
        $this->service->suspend($active2);

        $this->assertSame(1, $this->service->countByProductState('Archivd', ProductActivationState::Active));
        $this->assertSame(1, $this->service->countByProductState('Archivd', ProductActivationState::Suspended));
        $this->assertSame(0, $this->service->countByProductState('Archivd', ProductActivationState::Expired));
    }

    public function test_ensure_implicit_active_lazy_creates(): void
    {
        $user = $this->userWithRole('User');

        $activation = $this->service->ensureImplicitActive($user, 'Archivd');

        $this->assertSame(ProductActivationState::Active, $activation->state);
        $this->assertSame('lazy_create', $activation->source);
    }

    private function userWithRole(string $role, string $email = 'user@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
