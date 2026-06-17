@extends('adminlte4.layouts.app')

@section('title', __('approval.my_pending_approvals'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('approval.my_pending_approvals') }}</h3>
                </div>
                <div class="card-body">
                    @if(isset($error))
                        <div class="alert alert-danger">{{ $error }}</div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if(count($approvals) === 0)
                        <div class="alert alert-info">
                            {{ __('approval.no_pending_approvals') }}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
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
                                                <a href="{{ route('approvals.show', $approval['document_id']) }}" 
                                                   class="btn btn-sm btn-info">
                                                    {{ __('approval.view_details') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
