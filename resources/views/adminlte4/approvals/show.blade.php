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
        <!-- Instance Info -->
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

        <!-- Steps -->
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
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="approveSlot('{{ $approver['id'] }}')">
                                                    {{ __('approval.approve') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="rejectSlot('{{ $approver['id'] }}')">
                                                    {{ __('approval.reject') }}
                                                </button>
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

    <!-- Approve/Reject Modals -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="rejectForm" method="POST">
                @csrf
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
            </form>
        </div>
    </div>

    <script>
    function approveSlot(slotId) {
        console.log('approveSlot called with:', slotId);
        if (confirm('{{ __('approval.confirm_approve') }}')) {
            console.log('User confirmed approve');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/approvals/slots/${slotId}/approve`;
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            console.log('Form created, about to submit:', form.action);
            form.submit();
        }
    }

    function rejectSlot(slotId) {
        console.log('rejectSlot called with:', slotId);
        const form = document.getElementById('rejectForm');
        form.action = `/approvals/slots/${slotId}/reject`;
        console.log('Opening modal, form action:', form.action);
        if (typeof $ !== 'undefined') {
            $('#rejectModal').modal('show');
        } else {
            // Fallback if jQuery not loaded
            document.getElementById('rejectModal').classList.add('show');
            document.getElementById('rejectModal').style.display = 'block';
        }
    }

    // Debug: Check if buttons exist
    console.log('Approval script loaded');
    const approveButtons = document.querySelectorAll('button[onclick^="approveSlot"]');
    const rejectButtons = document.querySelectorAll('button[onclick^="rejectSlot"]');
    console.log('Approve buttons found:', approveButtons.length);
    console.log('Reject buttons found:', rejectButtons.length);
    </script>
</x-adminlte4::layouts.app>
