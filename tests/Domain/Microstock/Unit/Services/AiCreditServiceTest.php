<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiCreditServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_credit_from_purchase_adds_balance_once(): void
    {
        $user = $this->userWithRole('User', 'credit@example.com');
        $service = Service::factory()->microstockAddonAiCredits5()->create();

        $purchase = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'charge_amount' => 5.00,
            'status' => PurchaseStatus::Pending,
        ]);

        $credits = app(AiCreditService::class);
        $credits->creditFromPurchase($purchase);
        $credits->creditFromPurchase($purchase);

        $this->assertSame(5_000_000, $credits->balance((string) $user->id));
        $this->assertSame(1, AiCreditTransaction::query()->where('type', AiCreditTransactionType::Purchase)->count());
    }

    public function test_debit_allows_multiple_settlement_debits(): void
    {
        $user = $this->userWithRole('User', 'debit@example.com');
        app(AiCreditService::class)->addCredit(
            (string) $user->id,
            1_000_000,
            AiCreditTransactionType::Adjustment,
        );

        $credits = app(AiCreditService::class);
        $credits->debit((string) $user->id, 200_000, 'asset_metadata', 'meta-1');
        $credits->debit((string) $user->id, 100_000, 'asset_metadata', 'meta-1');

        $this->assertSame(700_000, $credits->balance((string) $user->id));
        $this->assertSame(2, AiCreditTransaction::query()->where('type', AiCreditTransactionType::AnalysisDebit)->count());
    }

    public function test_debit_allows_negative_balance(): void
    {
        $user = $this->userWithRole('User', 'negative@example.com');
        app(AiCreditService::class)->addCredit(
            (string) $user->id,
            100_000,
            AiCreditTransactionType::Purchase,
        );

        app(AiCreditService::class)->debit((string) $user->id, 200_000, 'asset_metadata', 'meta-neg', AiCreditTransactionType::Adjustment);

        $this->assertSame(-100_000, app(AiCreditService::class)->balance((string) $user->id));
    }

    public function test_ignores_non_ai_credit_purchase(): void
    {
        $user = $this->userWithRole('User', 'skip@example.com');
        $service = Service::factory()->microstockPlanFree()->create();

        $purchase = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'charge_amount' => 0,
            'status' => PurchaseStatus::Pending,
        ]);

        app(AiCreditService::class)->creditFromPurchase($purchase);

        $this->assertSame(0, app(AiCreditService::class)->balance((string) $user->id));
    }

    private function userWithRole(string $role, string $email): \App\Domain\Identity\Models\User
    {
        $user = \App\Domain\Identity\Models\User::factory()->create([
            'email' => $email,
            'name' => $email,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
