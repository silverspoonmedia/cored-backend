<x-adminlte4::layouts.app :title="__('approval.my_pending_approvals')">
    <x-adminlte4::card :title="__('approval.my_pending_approvals')">
        @if(isset($error))
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(count($approvals) === 0)
            <div class="alert alert-info">
                {{ __('approval.no_pending_approvals') }}
            </div>
        @else
            <x-adminlte4::form.ajax :action="route('approvals.batch-approve')" id="bulkApproveForm">

                <div class="mb-3 d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-success" onclick="submitBulkApprove()">
                        {{ __('approval.bulk_approve') }}
                    </button>
                    <small class="text-muted">{{ __('approval.select_all') }}</small>
                </div>

                <div class="mb-3">
                    <label for="bulkNotes" class="form-label">{{ __('approval.bulk_approve_notes') }}</label>
                    <textarea id="bulkNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllApprovals">
                                </th>
                                <th>{{ __('approval.document_type') }}</th>
                                <th>{{ __('approval.step') }}</th>
                                <th>{{ __('approval.slot_label') }}</th>
                                <th>{{ __('approval.submitted_by') }}</th>
                                <th>{{ __('approval.submitted_at') }}</th>
                                <th>{{ __('approval.sla_target') }}</th>
                                <th>{{ __('approval.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvals as $approval)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="slot_ids[]" value="{{ $approval['id'] }}" class="approval-checkbox">
                                    </td>
                                    <td>{{ $approval['document_type'] }}</td>
                                    <td>
                                        <span class="badge bg-primary">
                                            {{ $approval['step_order'] }}. {{ $approval['step_name'] }}
                                        </span>
                                    </td>
                                    <td>{{ $approval['slot_label'] }}</td>
                                    <td>{{ $approval['submitted_by'] ?? '-' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($approval['submitted_at'])->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($approval['sla_target_date'])
                                            {{ \Carbon\Carbon::parse($approval['sla_target_date'])->format('Y-m-d H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('approvals.show', $approval['instance_id']) }}" class="btn btn-sm btn-info">
                                            {{ __('approval.view_details') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-adminlte4::form.ajax>
        @endif
    </x-adminlte4::card>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllApprovals');
        const checkboxes = document.querySelectorAll('.approval-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        }
    });

    function submitBulkApprove() {
        const checked = document.querySelectorAll('.approval-checkbox:checked');

        if (checked.length === 0) {
            alert('{{ __('approval.bulk_approve_requires_selection') }}');
            return;
        }

        if (!confirm('{{ __('approval.confirm_approve') }}')) {
            return;
        }

        document.getElementById('bulkApproveForm').submit();
    }
    </script>
</x-adminlte4::layouts.app>
