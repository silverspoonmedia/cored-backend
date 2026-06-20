<x-adminlte4::layouts.app :title="__('approval.workflow_edit')">
    <x-adminlte4::card :title="__('approval.workflow_edit')">
        <form method="POST" action="{{ route('admin.approval-workflows.update', $workflowData['id']) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">{{ __('approval.document_type') }}</label>
                <input type="text" name="document_type" class="form-control" value="{{ old('document_type', $workflowData['document_type'] ?? 'ORDER') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">{{ __('approval.name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $workflowData['name'] ?? '') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">{{ __('approval.description') }}</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $workflowData['description'] ?? '') }}</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">{{ __('approval.version') }}</label>
                <input type="number" name="version" class="form-control" value="{{ old('version', $workflowData['version'] ?? 1) }}" min="1">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ ($workflowData['is_active'] ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="isActive">{{ __('approval.active') }}</label>
            </div>

            <button class="btn btn-primary">{{ __('approval.save') }}</button>
            <a href="{{ route('admin.approval-workflows.show', $workflowData['id']) }}" class="btn btn-secondary">{{ __('approval.cancel') }}</a>
        </form>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>