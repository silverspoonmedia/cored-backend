<x-adminlte4::layouts.app :title="__('web/vtual.title')">
    <x-adminlte4::card :title="__('web/vtual.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.foundation_note') }}</p>

        <div class="row g-3">
            <div class="col-lg-6">
                <h5 class="mb-2">{{ __('web/vtual.creators') }}</h5>
                <form method="POST" action="{{ route('vtual.creators.store') }}" class="border rounded p-3 mb-3">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">{{ __('web/vtual.display_name') }}</label>
                        <input type="text" name="display_name" class="form-control form-control-sm" required maxlength="120">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('web/vtual.slug') }}</label>
                        <input type="text" name="slug" class="form-control form-control-sm" maxlength="120">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('web/vtual.add_creator') }}</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/vtual.display_name') }}</th>
                                <th>Slug</th>
                                <th>{{ __('web/vtual.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($creators as $creator)
                                <tr>
                                    <td>{{ $creator['display_name'] ?? '' }}</td>
                                    <td><code>{{ $creator['slug'] ?? '' }}</code></td>
                                    <td><span class="badge text-bg-light border">{{ $creator['status'] ?? '' }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vtual.creators.show', $creator['id']) }}" class="btn btn-outline-info btn-sm">
                                            {{ __('web/vtual.view') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-adminlte4::empty-state icon="bi-broadcast" :title="__('web/vtual.empty_creators')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-6">
                <h5 class="mb-2">{{ __('web/vtual.managements') }}</h5>
                <form method="POST" action="{{ route('vtual.managements.store') }}" class="border rounded p-3 mb-3">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">{{ __('web/vtual.name') }}</label>
                        <input type="text" name="name" class="form-control form-control-sm" required maxlength="120">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('web/vtual.slug') }}</label>
                        <input type="text" name="slug" class="form-control form-control-sm" maxlength="120">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('web/vtual.add_management') }}</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/vtual.name') }}</th>
                                <th>Slug</th>
                                <th>{{ __('web/vtual.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($managements as $management)
                                <tr>
                                    <td>{{ $management['name'] ?? '' }}</td>
                                    <td><code>{{ $management['slug'] ?? '' }}</code></td>
                                    <td><span class="badge text-bg-light border">{{ $management['status'] ?? '' }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-adminlte4::empty-state icon="bi-people" :title="__('web/vtual.empty_managements')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5 class="mt-4 mb-2">{{ __('web/vtual.platforms') }}</h5>
                <ul class="list-group list-group-flush border rounded">
                    @foreach ($platforms as $platform)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $platform['name'] ?? $platform['key'] ?? '' }}</span>
                            @if (!empty($platform['enabled']))
                                <span class="badge text-bg-success">{{ __('web/vtual.enabled') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('web/vtual.disabled') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
