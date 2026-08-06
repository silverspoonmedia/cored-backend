<x-adminlte4::layouts.app :title="__('web/vtual.probe.title')">
    <x-adminlte4::card :title="__('web/vtual.probe.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.probe.subtitle') }}</p>

        <div class="mb-3 d-flex flex-wrap gap-2">
            <a href="{{ route('vtual.platform-credentials.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual-platform-credentials.back_to_platforms') }}
            </a>
        </div>

        <x-adminlte4::form.ajax :action="route('vtual.platform-credentials.probe.run')" method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4" data-form-field="platform">
                    <label class="form-label" for="platform">{{ __('web/vtual.platform') }}</label>
                    <select name="platform" id="platform" class="form-select" required>
                        <option value="youtube" @selected(($input['platform'] ?? '') === 'youtube')>youtube</option>
                        <option value="twitch" @selected(($input['platform'] ?? '') === 'twitch')>twitch</option>
                    </select>
                    <x-adminlte4::form.error name="platform" />
                </div>

                <div class="col-md-8" data-form-field="method">
                    <label class="form-label" for="method">{{ __('web/vtual.probe.method') }}</label>
                    <select name="method" id="method" class="form-select" required>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(($input['method'] ?? '') === $method)>
                                {{ __('web/vtual.probe.method_'.$method) }}
                            </option>
                        @endforeach
                    </select>
                    <x-adminlte4::form.error name="method" />
                </div>

                <div class="col-12" data-form-field="ids">
                    <label class="form-label" for="ids">{{ __('web/vtual.probe.ids') }}</label>
                    <input type="text"
                           name="ids"
                           id="ids"
                           class="form-control font-monospace"
                           value="{{ $input['ids'] ?? '' }}"
                           placeholder="id1, id2, id3">
                    <div class="form-text">{{ __('web/vtual.probe.ids_help') }}</div>
                    <x-adminlte4::form.error name="ids" />
                </div>

                <div class="col-md-6" data-form-field="handle">
                    <label class="form-label" for="handle">{{ __('web/vtual.probe.handle') }}</label>
                    <input type="text"
                           name="handle"
                           id="handle"
                           class="form-control font-monospace"
                           value="{{ $input['handle'] ?? '' }}"
                           placeholder="@channel or login">
                    <div class="form-text">{{ __('web/vtual.probe.handle_help') }}</div>
                    <x-adminlte4::form.error name="handle" />
                </div>

                <div class="col-md-6" data-form-field="parts">
                    <label class="form-label" for="parts">{{ __('web/vtual.probe.parts') }}</label>
                    <input type="text"
                           name="parts"
                           id="parts"
                           class="form-control font-monospace"
                           value="{{ $input['parts'] ?? '' }}"
                           placeholder="snippet,contentDetails,status">
                    <div class="form-text">{{ __('web/vtual.probe.parts_help') }}</div>
                    <x-adminlte4::form.error name="parts" />
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox"
                               name="chunked"
                               id="chunked"
                               value="1"
                               class="form-check-input"
                               @checked(! empty($input['chunked']))>
                        <label class="form-check-label" for="chunked">
                            {{ __('web/vtual.probe.chunked') }}
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <x-adminlte4::form.submit :label="__('web/vtual.probe.run')" class="btn btn-primary" />
                </div>
            </div>
        </x-adminlte4::form.ajax>

        @if ($error)
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('web/vtual.probe.error') }}:</strong> {{ $error }}
            </div>
        @endif

        @if ($resultJson)
            <h5 class="mb-2">{{ __('web/vtual.probe.result') }}</h5>
            <pre class="bg-body-secondary border rounded p-3 small mb-0 vh-50 overflow-auto" role="status" aria-live="polite"><code>{{ $resultJson }}</code></pre>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
