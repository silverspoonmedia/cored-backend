<x-adminlte4::layouts.app :title="__('approval.approval_details')">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(!$instance)
        <div class="alert alert-danger">{{ __('approval.instance_not_found') }}</div>
    @else
        <x-adminlte4::card :title="__('approval.approval_details')">
            <x-slot name="tools">
                <span class="badge badge-{{ $instance['status'] === 'PENDING' ? 'warning' : ($instance['status'] === 'APPROVED' ? 'success' : 'danger') }}">
                    {{ $instance['status'] }}
                </span>
            </x-slot>

            <dl class="row">
                <dt class="col-sm-3">{{ __('approval.document_type') }}:</dt>
                <dd class="col-sm-9">{{ $instance['document_type'] }}</dd>

                <dt class="col-sm-3">{{ __('approval.workflow') }}:</dt>
                <dd class="col-sm-9">{{ $instance['workflow']['name'] }} (v{{ $instance['workflow']['version'] }})</dd>

                <dt class="col-sm-3">{{ __('approval.submitted_by') }}:</dt>
                <dd class="col-sm-9">{{ $instance['submitted_by']['name'] }}</dd>

                <dt class="col-sm-3">{{ __('approval.submitted_at') }}:</dt>
                <dd class="col-sm-9">{{ \Carbon\Carbon::parse($instance['submitted_at'])->format('Y-m-d H:i:s') }}</dd>

                @if($instance['completed_at'])
                    <dt class="col-sm-3">{{ __('approval.completed_at') }}:</dt>
                    <dd class="col-sm-9">{{ \Carbon\Carbon::parse($instance['completed_at'])->format('Y-m-d H:i:s') }}</dd>
                @endif
            </dl>
        </x-adminlte4::card>

        <x-adminlte4::card :title="__('approval.approval_steps')">
            @foreach($instance['steps'] as $step)
                <div class="card mb-3 {{ $step['status'] === 'IN_PROGRESS' ? 'border-primary' : '' }}">
                    <div class="card-header">
                        <h5>
                            {{ $step['order'] }}. {{ $step['name'] }}
                            <span class="badge bg-{{ $step['status'] === 'APPROVED' ? 'success' : ($step['status'] === 'IN_PROGRESS' ? 'primary' : 'secondary') }}">
                                {{ $step['status'] }}
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('approval.approver') }}</th>
                                    <th>{{ __('approval.assigned_to') }}</th>
                                    <th>{{ __('approval.status') }}</th>
                                    <th>{{ __('approval.notes') }}</th>
                                    <th>{{ __('approval.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($step['approvers'] as $approver)
                                    <tr>
                                        <td>{{ $approver['slot_label'] }}</td>
                                        <td>{{ $approver['assigned_to']['name'] ?? '-' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $approver['status'] === 'APPROVED' ? 'success' : ($approver['status'] === 'WAITING' ? 'warning' : 'danger') }}">
                                                {{ $approver['status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $approver['notes'] ?? '-' }}</td>
                                        <td>
                                            @if($approver['status'] === 'WAITING' && $approver['assigned_to'] && $approver['assigned_to']['id'] === auth()->id())
                                                <button type="button" class="btn btn-sm btn-success" onclick="approveSlot('{{ $approver['id'] }}')">
                                                    {{ __('approval.approve') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="rejectSlot('{{ $approver['id'] }}')">
                                                    {{ __('approval.reject') }}
                                                </button>
                                                @if($approver['allow_reassignment'] && count($approver['reassignment_candidates']) > 0)
                                                    <button type="button"
                                                            class="btn btn-sm btn-warning"
                                                            onclick='reassignSlot(@json($approver["id"]), @json($approver["reassignment_candidates"]))'>
                                                        {{ __('approval.reassign') }}
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </x-adminlte4::card>
    @endif

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <x-adminlte4::form.ajax :action="''" id="rejectForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('approval.reject_confirmation') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ __('approval.rejection_notes') }} *</label>
                            <textarea name="notes" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('approval.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('approval.confirm_reject') }}</button>
                    </div>
                </div>
            </x-adminlte4::form.ajax>
        </div>
    </div>

    <div class="modal fade" id="reassignModal" tabindex="-1">
        <div class="modal-dialog">
            <x-adminlte4::form.ajax :action="''" id="reassignForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('approval.reassign_confirmation') }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ __('approval.reassign_select_user') }} *</label>
                            <select id="reassignUserId" name="user_id" class="form-select" data-monolith-select2="1" data-placeholder="--" required></select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('approval.reassign_reason') }} *</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('approval.cancel') }}</button>
                        <button type="submit" class="btn btn-warning">{{ __('approval.confirm_reassign') }}</button>
                    </div>
                </div>
            </x-adminlte4::form.ajax>
        </div>
    </div>

    <x-slot:scripts>
        <x-adminlte4::partials.select2-scripts />

        <script>
        function approveSlot(slotId) {
            if (!confirm('{{ __('approval.confirm_approve') }}')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/approvals/slots/${slotId}/approve`;
            form.innerHTML = `@csrf`;
            document.body.appendChild(form);
            form.submit();
        }

        function rejectSlot(slotId) {
            const form = document.getElementById('rejectForm');
            form.action = `/approvals/slots/${slotId}/reject`;
            $('#rejectModal').modal('show');
        }

        function reassignSlot(slotId, candidates) {
            const form = document.getElementById('reassignForm');
            const select = document.getElementById('reassignUserId');
            form.action = `/approvals/slots/${slotId}/reassign`;
            select.innerHTML = '<option value="">--</option>';

            candidates.forEach(function (candidate) {
                const option = document.createElement('option');
                option.value = candidate.id;
                option.textContent = candidate.name;
                select.appendChild(option);
            });

            if (window.MonolithSelect2) {
                window.MonolithSelect2.initElement(select);
            }

            $('#reassignModal').modal('show');
        }
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
