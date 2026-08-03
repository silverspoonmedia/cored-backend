<x-adminlte4::layouts.app :title="__('domain-access.title')">
    <x-adminlte4::card :title="__('domain-access.title')" :subtitle="__('domain-access.subtitle')">
        @if ($domains->isEmpty())
            <x-adminlte4::empty-state icon="bi bi-shield-lock" :title="__('domain-access.empty')" />
        @else
            <div
                class="row g-3"
                data-domain-access
                data-update-url-template="{{ route('domain-access.update', ['productKey' => '__KEY__']) }}"
                data-messages="{{ json_encode([
                    'updated' => __('domain-access.toggle_updated'),
                    'failed' => __('domain-access.toggle_failed'),
                    'public' => __('domain-access.visibility_public'),
                    'private' => __('domain-access.visibility_private'),
                    'publicHint' => __('domain-access.public_hint'),
                    'privateHint' => __('domain-access.private_hint'),
                ]) }}"
            >
                @foreach ($domains as $domain)
                    @php
                        $key = $domain['key'] ?? '';
                        $label = $domain['label'] ?? $key;
                        $visibility = $domain['visibility'] ?? 'public';
                        $betaRole = $domain['beta_role'] ?? '';
                        $isPrivate = $visibility === 'private';
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm" data-domain-card data-domain-key="{{ $key }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h5 class="card-title mb-0">{{ $label }}</h5>
                                    <span
                                        class="badge text-bg-{{ $isPrivate ? 'warning' : 'success' }}"
                                        data-domain-visibility-badge
                                    >
                                        {{ $isPrivate ? __('domain-access.visibility_private') : __('domain-access.visibility_public') }}
                                    </span>
                                </div>

                                <p class="text-muted small mb-2"><code>{{ $key }}</code></p>

                                <p class="text-muted small mb-3" data-domain-hint>
                                    {{ $isPrivate ? __('domain-access.private_hint') : __('domain-access.public_hint') }}
                                </p>

                                <p class="text-muted small mb-3">
                                    {{ __('domain-access.beta_role', ['role' => $betaRole]) }}
                                </p>

                                <div class="mt-auto">
                                    @can('manage domain access')
                                        <div class="form-check form-switch m-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                id="domain-toggle-{{ $key }}"
                                                data-domain-toggle="{{ $key }}"
                                                @checked($isPrivate)
                                            >
                                            <label class="form-check-label small" for="domain-toggle-{{ $key }}">
                                                {{ __('domain-access.visibility_private') }}
                                            </label>
                                        </div>
                                    @else
                                        <span class="badge text-bg-{{ $isPrivate ? 'warning' : 'success' }}">
                                            {{ $isPrivate ? __('domain-access.visibility_private') : __('domain-access.visibility_public') }}
                                        </span>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="monolith-toast-container" data-domain-access-toast role="status" aria-live="polite"></div>
            </div>
        @endif
    </x-adminlte4::card>

    @vite(['resources/js/domain-access/toggle.js'])
</x-adminlte4::layouts.app>
