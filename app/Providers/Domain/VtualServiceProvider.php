<?php

namespace App\Providers\Domain;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Models\VtualEvent;
use App\Domain\Vtual\Policies\ChannelPolicy;
use App\Domain\Vtual\Policies\CreatorPolicy;
use App\Domain\Vtual\Policies\ManagementPolicy;
use App\Domain\Vtual\Policies\PlatformCredentialPolicy;
use App\Domain\Vtual\Support\PublicLandingCache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class VtualServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Creator::class, CreatorPolicy::class);
        Gate::policy(Management::class, ManagementPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(PlatformCredential::class, PlatformCredentialPolicy::class);

        // Forever landing cache: flush on any write that can change the payload
        // (cache policy §3 — invalidate on data change, rebuild on next read).
        $flushLanding = static fn () => PublicLandingCache::flush();
        foreach ([Content::class, Creator::class, Channel::class, VtualEvent::class] as $model) {
            $model::saved($flushLanding);
            $model::deleted($flushLanding);
        }
    }
}
