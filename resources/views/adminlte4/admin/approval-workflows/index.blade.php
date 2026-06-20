<x-adminlte4::layouts.app :title="__('approval.workflow_admin')">
    <x-adminlte4::card :title="__('approval.workflow_list')">
        <x-slot name="tools">
            <a href="{{ route('admin.approval-workflows.create') }}" class="btn btn-primary btn-sm">
                {{ __('approval.workflow_create') }}
            </a>
        </x-slot>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>{{ __('approval.document_type') }}</th>
                        <th>{{ __('approval.name') }}</th>
                        <th>{{ __('approval.version') }}</th>
                        <th>{{ __('approval.status') }}</th>
                        <th>{{ __('approval.steps_count') }}</th>
                        <th>{{ __('approval.instances_count') }}</th>
                        <th>{{ __('approval.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workflows as $workflow)
                        <tr>
                            <td><code>{{ $workflow['document_type'] }}</code></td>
                            <td>{{ $workflow['name'] }}</td>
                            <td>v{{ $workflow['version'] }}</td>
                            <td>
                                @if($workflow['is_active'])
                                    <span class="badge bg-success">{{ __('approval.active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('approval.inactive') }}</span>
                                @endif
                            </td>
                            <td>{{ $workflow['steps_count'] }}</td>
                            <td>{{ $workflow['instances_count'] }}</td>
                            <td>
                                <a href="{{ route('admin.approval-workflows.show', $workflow['id']) }}" class="btn btn-sm btn-info">
                                    {{ __('approval.view_details') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">{{ __('approval.no_workflows') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
