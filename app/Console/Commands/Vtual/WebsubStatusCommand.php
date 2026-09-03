<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Models\WebsubSubscription;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:websub-status')]
class WebsubStatusCommand extends Command
{
    protected $signature = 'vtual:websub-status
                            {--channel= : Limit to one channel uuid}';

    protected $description = 'Report vTual YouTube WebSub subscription state per channel (staging validation aid)';

    public function handle(): int
    {
        $query = WebsubSubscription::query()
            ->with('channel')
            ->orderBy('status')
            ->orderBy('lease_expires_at');

        $channelOpt = $this->option('channel');
        if (is_string($channelOpt) && $channelOpt !== '') {
            $query->where('channel_id', $channelOpt);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('No WebSub subscriptions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['channel', 'external_id', 'status', 'lease_expires_at', 'last_notify_at', 'last_error'],
            $rows->map(fn (WebsubSubscription $sub): array => [
                (string) $sub->channel_id,
                (string) ($sub->channel?->external_id ?? '?'),
                $sub->status instanceof WebsubSubscriptionStatus ? $sub->status->value : (string) $sub->status,
                $sub->lease_expires_at?->toDateTimeString() ?? '-',
                $sub->last_notify_at?->toDateTimeString() ?? 'never',
                $sub->last_error !== null ? mb_substr((string) $sub->last_error, 0, 60) : '-',
            ])->all(),
        );

        $neverNotified = $rows->filter(
            fn (WebsubSubscription $sub): bool => $sub->status === WebsubSubscriptionStatus::Active
                && $sub->last_notify_at === null,
        )->count();

        $this->line(sprintf(
            'total=%d active_never_notified=%d',
            $rows->count(),
            $neverNotified,
        ));

        if ($neverNotified > 0) {
            $this->warn(sprintf(
                '%d active subscription(s) have never received a push. Publish a video on staging and re-run to validate the push path.',
                $neverNotified,
            ));
        }

        return self::SUCCESS;
    }
}
