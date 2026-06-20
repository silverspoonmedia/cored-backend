<x-adminlte4::layouts.app :title="__('approval.workflow_details')">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $workflowData['name'] ?? '-' }}</h5>
                    <span class="badge bg-{{ ($workflowData['is_active'] ?? false) ? 'success' : 'secondary' }}">
                        {{ ($workflowData['is_active'] ?? false) ? __('approval.active') : __('approval.inactive') }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('approval.document_type') }}</dt>
                        <dd class="col-sm-8"><code>{{ $workflowData['document_type'] ?? '-' }}</code></dd>

                        <dt class="col-sm-4">{{ __('approval.version') }}</dt>
                        <dd class="col-sm-8">v{{ $workflowData['version'] ?? 1 }}</dd>

                        <dt class="col-sm-4">{{ __('approval.description') }}</dt>
                        <dd class="col-sm-8">{{ $workflowData['description'] ?? '-' }}</dd>

                        <dt class="col-sm-4">{{ __('approval.instances_count') }}</dt>
                        <dd class="col-sm-8">{{ $workflowData['instances_count'] ?? 0 }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.approval-workflows.edit', $workflowData['id']) }}" class="btn btn-sm btn-primary">{{ __('approval.edit') }}</a>

                    @if($workflowData['is_active'] ?? false)
                        <form method="POST" action="{{ route('admin.approval-workflows.deactivate', $workflowData['id']) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-sm btn-warning">{{ __('approval.deactivate') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.approval-workflows.activate', $workflowData['id']) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-sm btn-success">{{ __('approval.activate') }}</button>
                        </form>
                    @endif

                    <a href="{{ route('admin.approval-workflows.index') }}" class="btn btn-sm btn-secondary">{{ __('approval.back') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('approval.workflow_steps') }}</h5>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStepModal">
                {{ __('approval.workflow_step_create') }}
            </button>
        </div>
        <div class="card-body">
            @if(empty($workflowData['steps']))
                <p class="text-muted text-center">{{ __('approval.no_steps') }}</p>
            @else
                @foreach($workflowData['steps'] as $step)
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <span class="badge bg-primary me-2">{{ $step['step_order'] }}</span>
                                {{ $step['step_name'] }}
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addApproverModal" data-step-id="{{ $step['id'] }}" data-step-name="{{ $step['step_name'] }}">
                                    {{ __('approval.add_approver') }}
                                </button>
                                <form method="POST" action="{{ route('admin.approval-workflows.steps.destroy', [$workflowData['id'], $step['id']]) }}" style="display:inline" onsubmit="return confirm('{{ __('approval.confirm_delete') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('approval.delete') }}</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-3"><strong>{{ __('approval.parallel_mode') }}:</strong></div>
                                <div class="col-md-9"><code>{{ $step['parallel_mode'] }}</code></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-3"><strong>{{ __('approval.reject_action') }}:</strong></div>
                                <div class="col-md-9">{{ $step['reject_action'] }}</div>
                            </div>
                            @if($step['reject_action'] === 'BACK_TO_STEP' && $step['reject_target_step_order'])
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>{{ __('approval.reject_target_step') }}:</strong></div>
                                    <div class="col-md-9">{{ $step['reject_target_step_order'] }}</div>
                                </div>
                            @endif
                            @if($step['condition'])
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>{{ __('approval.condition') }}:</strong></div>
                                    <div class="col-md-9"><code>{{ $step['condition']['field'] }} {{ $step['condition']['op'] }} {{ $step['condition']['value'] }}</code></div>
                                </div>
                            @endif
                            <div class="row mb-2">
                                <div class="col-md-3"><strong>{{ __('approval.reminder_days') }}:</strong></div>
                                <div class="col-md-9">{{ $step['reminder_after_days'] }}</div>
                            </div>
                            @if($step['escalate_after_days'])
                                <div class="row mb-2">
                                    <div class="col-md-3"><strong>{{ __('approval.escalate_after_days') }}:</strong></div>
                                    <div class="col-md-9">{{ $step['escalate_after_days'] }} days (to {{ $step['escalate_to_role'] }})</div>
                                </div>
                            @endif
                            <div class="row mb-2">
                                <div class="col-md-3"><strong>{{ __('approval.allow_reassignment') }}:</strong></div>
                                <div class="col-md-9">{{ $step['allow_reassignment'] ? __('approval.yes') : __('approval.no') }}</div>
                            </div>

                            <h6 class="mt-3">{{ __('approval.approvers') }}</h6>
                            @if(empty($step['approvers']))
                                <p class="text-muted small">{{ __('approval.no_approvers') }}</p>
                            @else
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('approval.slot_label') }}</th>
                                            <th>{{ __('approval.required_role') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($step['approvers'] as $approver)
                                            <tr>
                                                <td>{{ $approver['slot_label'] }}</td>
                                                <td><code>{{ $approver['required_role'] }}</code></td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.approval-workflows.steps.approvers.destroy', [$workflowData['id'], $step['id'], $approver['id']]) }}" style="display:inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger">{{ __('approval.delete') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="modal fade" id="addStepModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('admin.approval-workflows.steps.store', $workflowData['id']) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('approval.workflow_step_create') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('approval.step_order') }}</label>
                                <input type="number" name="step_order" class="form-control" value="{{ old('step_order', (count($workflowData['steps'] ?? []) + 1)) }}" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('approval.step_name') }}</label>
                                <input type="text" name="step_name" class="form-control" value="{{ old('step_name') }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.parallel_mode') }}</label>
                                <select name="parallel_mode" class="form-select" required>
                                    @foreach($meta['parallel_modes'] ?? ['UNANIMOUS', 'ANY_REJECT', 'MAJORITY_VOTE'] as $mode)
                                        <option value="{{ $mode }}" {{ old('parallel_mode') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.reject_action') }}</label>
                                <select name="reject_action" class="form-select" id="rejectActionSelect" required>
                                    @foreach($meta['reject_actions'] ?? ['STOP', 'BACK_TO_STEP'] as $action)
                                        <option value="{{ $action }}" {{ old('reject_action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="rejectTargetStepGroup" style="display:none">
                                <label class="form-label">{{ __('approval.reject_target_step') }}</label>
                                <input type="number" name="reject_target_step_order" class="form-control" value="{{ old('reject_target_step_order') }}" min="1">
                            </div>
                        </div>

                        <hr>
                        <h6>{{ __('approval.condition_optional') }}</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.condition_field') }}</label>
                                <input type="text" name="condition_field" class="form-control" value="{{ old('condition_field') }}" placeholder="e.g. grand_total">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.condition_op') }}</label>
                                <select name="condition_op" class="form-select">
                                    <option value=">=" {{ old('condition_op') === '>=' ? 'selected' : '' }}>&gt;=</option>
                                    <option value=">" {{ old('condition_op') === '>' ? 'selected' : '' }}>&gt;</option>
                                    <option value="<=" {{ old('condition_op') === '<=' ? 'selected' : '' }}>&lt;=</option>
                                    <option value="<" {{ old('condition_op') === '<' ? 'selected' : '' }}>&lt;</option>
                                    <option value="=" {{ old('condition_op') === '=' ? 'selected' : '' }}>=</option>
                                    <option value="!=" {{ old('condition_op') === '!=' ? 'selected' : '' }}>!=</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.condition_value') }}</label>
                                <input type="text" name="condition_value" class="form-control" value="{{ old('condition_value') }}" placeholder="e.g. 10000">
                            </div>
                        </div>

                        <hr>
                        <h6>{{ __('approval.reminder_and_escalation') }}</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.reminder_days') }}</label>
                                <input type="number" name="reminder_after_days" class="form-control" value="{{ old('reminder_after_days', 3) }}" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.escalate_after_days') }}</label>
                                <input type="number" name="escalate_after_days" class="form-control" value="{{ old('escalate_after_days') }}" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('approval.escalate_to_role') }}</label>
                                <select name="escalate_to_role" class="form-select">
                                    <option value="">--</option>
                                    @foreach($meta['roles'] ?? [] as $role)
                                        <option value="{{ $role }}" {{ old('escalate_to_role') === $role ? 'selected' : '' }}>{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="allow_reassignment" class="form-check-input" id="allowReassignment" value="1" {{ old('allow_reassignment') ? 'checked' : '' }}>
                            <label class="form-check-label" for="allowReassignment">{{ __('approval.allow_reassignment') }}</label>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('approval.approve_set_doc_status') }}</label>
                                <input type="text" name="approve_set_doc_status" class="form-control" value="{{ old('approve_set_doc_status') }}" placeholder="e.g. APPROVED">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('approval.reject_set_doc_status') }}</label>
                                <input type="text" name="reject_set_doc_status" class="form-control" value="{{ old('reject_set_doc_status') }}" placeholder="e.g. REJECTED">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('approval.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('approval.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addApproverModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="addApproverForm" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('approval.add_approver') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('approval.required_role') }}</label>
                            <select name="required_role" class="form-select" required>
                                @foreach($meta['roles'] ?? [] as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('approval.slot_label') }}</label>
                            <input type="text" name="slot_label" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('approval.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('approval.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const rejectActionSelect = document.getElementById('rejectActionSelect');
        const rejectTargetGroup = document.getElementById('rejectTargetStepGroup');

        if (rejectActionSelect && rejectTargetGroup) {
            rejectActionSelect.addEventListener('change', function () {
                rejectTargetGroup.style.display = this.value === 'BACK_TO_STEP' ? 'block' : 'none';
            });
        }

        const addApproverModal = document.getElementById('addApproverModal');
        if (addApproverModal) {
            addApproverModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const stepId = button.dataset.stepId;
                const form = document.getElementById('addApproverForm');
                form.action = `/admin/approval-workflows/{{ $workflowData['id'] }}/steps/${stepId}/approvers`;
            });
        }
    });
    </script>
</x-adminlte4::layouts.app>
