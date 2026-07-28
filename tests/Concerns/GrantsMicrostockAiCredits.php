<?php

namespace Tests\Concerns;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Services\AiCreditService;

trait GrantsMicrostockAiCredits
{
    protected function grantMicrostockAiCredits(User $user, int $microUsd = 10_000_000): void
    {
        app(AiCreditService::class)->addCredit(
            (string) $user->id,
            $microUsd,
            AiCreditTransactionType::Adjustment,
            'test_grant',
            null,
        );
    }
}
