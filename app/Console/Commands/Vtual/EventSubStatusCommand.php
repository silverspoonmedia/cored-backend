<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Models\EventsubSubscription;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:eventsub-status')]
class EventSubStatusCommand extends Command
{
    protected $signature = 'vtual:eventsub-status
                            {--channel= : Limit to one channel uuid}';

    protected $description = 'Report vTual Twitch EventSub subscription state per channel (staging validation aid)';

    public function handle(): int
    {
        $query = EventsubSubscription::query()
            ->with('channel')
            ->orderBy('status')
            ->orderBy('lease_expires_at');

        $channelOpt = $this->option('channel');
        if (is_string($channelOpt) && $channelOpt !== '') {
            $query->where('channel_id', $channelOpt);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('No EventSub subscriptions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['channel', 'external_id', 'event_type', 'status', 'lease_expires_at', 'last_notify_at', 'last_error'],
            $rows->map(fn (EventsubSubscription $sub): array => [
                (string) $sub->channel_id,
                (string) ($sub->channel?->external_id ?? '?'),
                $sub->event_type instanceof \App\Domain\Vtual\Enums\TwitchEventSubType ? $sub->event_type->value : (string) $sub->event_type,
                $sub->status instanceof TwitchEventSubStatus ? $sub->status->value : (string) $sub->status,
                $sub->lease_expires_at?->toDateTimeString() ?? '-',
                $sub->last_notify_at?->toDateTimeString() ?? 'never',
                $sub->last_error !== null ? mb_substr((string) $sub->last_error, 0, 60) : '-',
            ])->all(),
        );

        $neverNotified = $rows->filter(
            fn (EventsubSubscription $sub): bool => $sub->status === TwitchEventSubStatus::Active
                && $sub->last_notify_at === null,
        )->count();

        $this->line(sprintf(
            'total=%d active_never_notified=%d',
            $rows->count(),
            $neverNotified,
        ));

        if ($neverNotified > 0) {
            $this->line(sprintf(
                '%d active subscription(s) have never received a push. Trigger a stream title change / go live on staging and re-run to validate the push path.',
                $neverNotified,
            ));
        }

        return self::SUCCESS;
    }
}
