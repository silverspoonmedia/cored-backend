<x-adminlte4::layouts.app :title="__('web/identity-connections.page_title')">
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/identity-connections.card_title')">
                <h5 class="h6 mb-2">{{ __('web/identity-connections.discord_heading') }}</h5>

                @if ($discordConnected)
                    <p class="text-muted small mb-3">{{ __('web/identity-connections.discord_connected') }}</p>
                    @if ($discordSyncedAt)
                        <p class="text-muted small mb-3">
                            {{ __('web/identity-connections.synced_at', ['at' => $discordSyncedAt->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
                        </p>
                    @endif

                    <h6 class="small fw-semibold mb-2">{{ __('web/identity-connections.linked_accounts_heading') }}</h6>
                    @if (count($discordConnections) === 0)
                        <p class="text-muted small mb-3">{{ __('web/identity-connections.linked_accounts_empty') }}</p>
                    @else
                        <ul class="list-group list-group-flush mb-3">
                            @foreach ($discordConnections as $connection)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="badge text-bg-light border">{{ strtoupper($connection['type'] ?? '') }}</span>
                                        <span class="ms-1">{{ $connection['name'] ?? $connection['id'] ?? '' }}</span>
                                    </span>
                                    <span class="d-flex gap-1 align-items-center">
                                        @if (($connection['revoked'] ?? false))
                                            <span class="badge text-bg-warning">{{ __('web/identity-connections.linked_revoked') }}</span>
                                        @endif
                                        <span class="badge {{ ($connection['verified'] ?? false) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ ($connection['verified'] ?? false)
                                                ? __('web/identity-connections.linked_verified')
                                                : __('web/identity-connections.linked_unverified') }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <x-adminlte4::form.ajax
                        :action="route('profile.connections.discord.disconnect')"
                        method="POST"
                        :showAlert="false"
                    >
                        <x-adminlte4::form.submit
                            :label="__('web/identity-connections.discord_disconnect')"
                            class="btn btn-outline-danger btn-sm"
                        />
                    </x-adminlte4::form.ajax>
                @elseif ($discordOAuthConfigured ?? false)
                    <p class="text-muted small mb-3">{{ __('web/identity-connections.discord_not_connected') }}</p>
                    <a href="{{ route('auth.discord.redirect', ['intent' => 'profile']) }}" class="btn btn-primary btn-sm">
                        {{ __('web/identity-connections.discord_connect') }}
                    </a>
                @else
                    <p class="text-muted small mb-3">{{ __('web/identity-connections.discord_not_configured') }}</p>
                @endif

                <div class="mt-3">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                        {{ __('ui.actions.cancel') }}
                    </a>
                </div>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
