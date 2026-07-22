<?php

namespace App\Providers\Domain;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Policies\ChannelPolicy;
use App\Domain\Vtual\Policies\CreatorPolicy;
use App\Domain\Vtual\Policies\ManagementPolicy;
use App\Domain\Vtual\Policies\PlatformCredentialPolicy;
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
    }
}
