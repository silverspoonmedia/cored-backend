<?php

namespace App\Domain\PaymentGateway\Repositories\Eloquent;

use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function all(): Collection
    {
        return Subscription::query()->orderByDesc('created_at')->get();
    }

    public function findOrFail(string $id): Subscription
    {
        return Subscription::query()->with('invoice')->findOrFail($id);
    }

    public function create(array $attributes): Subscription
    {
        if (! isset($attributes['subscription_number'])) {
            $attributes['subscription_number'] = Subscription::generateSubscriptionNumber();
        }

        return Subscription::query()->create($attributes);
    }

    public function update(string $id, array $attributes): Subscription
    {
        $record = $this->findOrFail($id);
        $record->update($attributes);

        return $record->fresh(['invoice']);
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
