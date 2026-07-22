<x-adminlte4::layouts.app :title="__('web/vtual.probe.title')">
    <x-adminlte4::card :title="__('web/vtual.probe.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.probe.subtitle') }}</p>

        <div class="mb-3 d-flex flex-wrap gap-2">
            <a href="{{ route('vtual.admin.credentials.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('web/vtual.credentials.back_platforms') }}
            </a>
        </div>

        @if ($error)
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('web/vtual.probe.error') }}:</strong> {{ $error }}
            </div>
        @endif

        <form method="POST" action="{{ route('vtual.admin.platform-api-probe.run') }}" class="mb-4">
            @csrf

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="platform">{{ __('web/vtual.platform') }}</label>
                    <select name="platform" id="platform" class="form-select @error('platform') is-invalid @enderror" required>
                        <option value="youtube" @selected(($input['platform'] ?? '') === 'youtube')>youtube</option>
                        <option value="twitch" @selected(($input['platform'] ?? '') === 'twitch')>twitch</option>
                    </select>
                    @error('platform')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="method">{{ __('web/vtual.probe.method') }}</label>
                    <select name="method" id="method" class="form-select @error('method') is-invalid @enderror" required>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(($input['method'] ?? '') === $method)>
                                {{ __('web/vtual.probe.method_'.$method) }}
                            </option>
                        @endforeach
                    </select>
                    @error('method')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="ids">{{ __('web/vtual.probe.ids') }}</label>
                    <input type="text"
                           name="ids"
                           id="ids"
                           class="form-control font-monospace @error('ids') is-invalid @enderror"
                           value="{{ $input['ids'] ?? '' }}"
                           placeholder="id1, id2, id3">
                    <div class="form-text">{{ __('web/vtual.probe.ids_help') }}</div>
                    @error('ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="handle">{{ __('web/vtual.probe.handle') }}</label>
                    <input type="text"
                           name="handle"
                           id="handle"
                           class="form-control font-monospace @error('handle') is-invalid @enderror"
                           value="{{ $input['handle'] ?? '' }}"
                           placeholder="@channel or login">
                    <div class="form-text">{{ __('web/vtual.probe.handle_help') }}</div>
                    @error('handle')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="parts">{{ __('web/vtual.probe.parts') }}</label>
                    <input type="text"
                           name="parts"
                           id="parts"
                           class="form-control font-monospace @error('parts') is-invalid @enderror"
                           value="{{ $input['parts'] ?? '' }}"
                           placeholder="snippet,contentDetails,status">
                    <div class="form-text">{{ __('web/vtual.probe.parts_help') }}</div>
                    @error('parts')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
                    <button type="submit" class="btn btn-primary">
                        {{ __('web/vtual.probe.run') }}
                    </button>
                </div>
            </div>
        </form>

        @if ($resultJson)
            <h5 class="mb-2">{{ __('web/vtual.probe.result') }}</h5>
            <pre class="bg-body-secondary border rounded p-3 small mb-0" style="max-height: 40rem; overflow: auto;"><code>{{ $resultJson }}</code></pre>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
