@extends('theme::layouts.1col')

@section('title', 'Disposal Action #' . $action->id)

@section('content')
<div class="container-fluid">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Disposal Action #{{ $action->id }}</h1>
        <a href="{{ route('records.disposal.queue') }}" class="btn btn-outline-secondary btn-sm">Back to Queue</a>
    </div>

    {{-- Header --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>{{ $action->io_title ?? 'Untitled (IO #' . $action->information_object_id . ')' }}</h5>
                    <p class="mb-1">
                        <strong>Action Type:</strong>
                        @php
                            $typeLabels = [
                                'destroy' => 'Destroy',
                                'transfer_archives' => 'Transfer to Archives',
                                'transfer_external' => 'Transfer External',
                                'retain_permanent' => 'Retain Permanently',
                                'review' => 'Review',
                            ];
                            $typeBadges = [
                                'destroy' => 'danger',
                                'transfer_archives' => 'info',
                                'transfer_external' => 'info',
                                'retain_permanent' => 'success',
                                'review' => 'warning',
                            ];
                        @endphp
                        <span class="badge bg-{{ $typeBadges[$action->action_type] ?? 'secondary' }}">{{ $typeLabels[$action->action_type] ?? $action->action_type }}</span>
                    </p>
                    <p class="mb-1">
                        <strong>Status:</strong>
                        @php
                            $statusBadges = [
                                'pending' => 'secondary',
                                'recommended' => 'info',
                                'approved' => 'primary',
                                'cleared' => 'success',
                                'executed' => 'dark',
                                'rejected' => 'danger',
                                'cancelled' => 'warning',
                                'retained' => 'success',
                            ];
                        @endphp
                        <span class="badge bg-{{ $statusBadges[$action->status] ?? 'secondary' }}">{{ ucfirst($action->status) }}</span>
                    </p>
                    @if ($action->transfer_destination)
                        <p class="mb-1"><strong>Transfer Destination:</strong> {{ $action->transfer_destination }}</p>
                    @endif
                </div>
                <div class="col-md-6 text-md-end">
                    @if ($action->has_active_hold)
                        <div class="alert alert-warning py-2 d-inline-block">
                            <i class="fas fa-gavel"></i> Active Legal Hold
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Approval Stepper --}}
    <div class="card mb-3">
        <div class="card-header"><strong>Approval Workflow</strong></div>
        <div class="card-body">
            <div class="row">
                {{-- Step 1: Initiated --}}
                <div class="col-md">
                    <div class="card {{ $action->initiated_at ? 'border-success' : 'border-secondary' }} mb-2">
                        <div class="card-body text-center py-3">
                            <div class="mb-1">
                                @if ($action->initiated_at)
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                @else
                                    <i class="fas fa-circle text-secondary fa-2x"></i>
                                @endif
                            </div>
                            <strong>1. Initiated</strong>
                            @if ($action->initiated_at)
                                <br><small>{{ \Carbon\Carbon::parse($action->initiated_at)->format('Y-m-d H:i') }}</small>
                                <br><small class="text-muted">{{ $action->initiated_by_name ?? 'User #' . $action->initiated_by }}</small>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Step 2: Recommended --}}
                <div class="col-md">
                    <div class="card {{ $action->recommended_at ? 'border-success' : 'border-secondary' }} mb-2">
                        <div class="card-body text-center py-3">
                            <div class="mb-1">
                                @if ($action->recommended_at)
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                @else
                                    <i class="fas fa-circle text-secondary fa-2x"></i>
                                @endif
                            </div>
                            <strong>2. Recommended</strong>
                            @if ($action->recommended_at)
                                <br><small>{{ \Carbon\Carbon::parse($action->recommended_at)->format('Y-m-d H:i') }}</small>
                                <br><small class="text-muted">{{ $action->recommended_by_name ?? '' }}</small>
                            @elseif ($action->status === 'pending')
                                <br>
                                <form method="POST" action="{{ route('records.disposal.recommend', $action->id) }}" class="mt-2">
                                    @csrf
                                    <input type="text" name="comment" class="form-control form-control-sm mb-1" placeholder="{{ __('Comment (optional)') }}">
                                    <button type="submit" class="btn btn-info btn-sm">{{ __('Recommend') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Step 3: Approved --}}
                <div class="col-md">
                    <div class="card {{ $action->approved_at ? 'border-success' : 'border-secondary' }} mb-2">
                        <div class="card-body text-center py-3">
                            <div class="mb-1">
                                @if ($action->approved_at)
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                @else
                                    <i class="fas fa-circle text-secondary fa-2x"></i>
                                @endif
                            </div>
                            <strong>3. Approved</strong>
                            @if ($action->approved_at)
                                <br><small>{{ \Carbon\Carbon::parse($action->approved_at)->format('Y-m-d H:i') }}</small>
                                <br><small class="text-muted">{{ $action->approved_by_name ?? '' }}</small>
                            @elseif (in_array($action->status, ['pending', 'recommended']))
                                <br>
                                <form method="POST" action="{{ route('records.disposal.approve', $action->id) }}" class="mt-2">
                                    @csrf
                                    <input type="text" name="comment" class="form-control form-control-sm mb-1" placeholder="{{ __('Comment (optional)') }}">
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Approve') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Step 4: Legal Cleared --}}
                <div class="col-md">
                    <div class="card {{ $action->legal_cleared ? 'border-success' : 'border-secondary' }} mb-2">
                        <div class="card-body text-center py-3">
                            <div class="mb-1">
                                @if ($action->legal_cleared)
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                @else
                                    <i class="fas fa-circle text-secondary fa-2x"></i>
                                @endif
                            </div>
                            <strong>4. Legal Cleared</strong>
                            @if ($action->legal_cleared)
                                <br><small>{{ $action->legal_cleared_at ? \Carbon\Carbon::parse($action->legal_cleared_at)->format('Y-m-d H:i') : '' }}</small>
                                <br><small class="text-muted">{{ $action->legal_cleared_by_name ?? '' }}</small>
                            @elseif ($action->status === 'approved')
                                @if ($action->has_active_hold)
                                    <br><small class="text-danger">Active legal hold prevents clearance</small>
                                @else
                                    <br>
                                    <form method="POST" action="{{ route('records.disposal.clearLegal', $action->id) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">{{ __('Clear Legal') }}</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Step 5: Executed --}}
                <div class="col-md">
                    <div class="card {{ $action->executed_at ? 'border-success' : 'border-secondary' }} mb-2">
                        <div class="card-body text-center py-3">
                            <div class="mb-1">
                                @if ($action->executed_at)
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                @else
                                    <i class="fas fa-circle text-secondary fa-2x"></i>
                                @endif
                            </div>
                            <strong>5. Executed</strong>
                            @if ($action->executed_at)
                                <br><small>{{ \Carbon\Carbon::parse($action->executed_at)->format('Y-m-d H:i') }}</small>
                                <br><small class="text-muted">{{ $action->executed_by_name ?? '' }}</small>
                                @if ($action->certificate)
                                    <br><small><a href="{{ route('records.disposal.verify', $action->id) }}">Certificate: {{ $action->certificate->certificate_number }}</a></small>
                                @endif
                            @elseif ($action->status === 'cleared')
                                <br>
                                <form method="POST" action="{{ route('records.disposal.execute', $action->id) }}" class="mt-2"
                                    onsubmit="return confirm('Are you sure you want to execute this disposal action? This cannot be undone.');">
                                    @csrf
                                    <button type="submit" class="btn btn-dark btn-sm">{{ __('Execute') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @if (!in_array($action->status, ['executed', 'cancelled', 'rejected', 'retained']))
        <div class="card mb-3">
            <div class="card-header"><strong>Actions</strong></div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    @if (in_array($action->status, ['pending', 'recommended', 'approved', 'cleared']))
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            {{ __('Reject') }}
                        </button>
                    @endif
                    @if (in_array($action->status, ['pending', 'recommended']))
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            {{ __('Cancel') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Notes --}}
    @if ($action->notes)
        <div class="card mb-3">
            <div class="card-header"><strong>Notes</strong></div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;">{{ $action->notes }}</pre>
            </div>
        </div>
    @endif

    {{-- Verification Button --}}
    @if ($action->status === 'executed' && $action->action_type === 'destroy')
        <div class="card mb-3">
            <div class="card-header"><strong>DoD 5015.2 Verification</strong></div>
            <div class="card-body">
                @if ($action->verification_status)
                    <p>
                        <strong>Last Verification:</strong>
                        @if ($action->verification_status === 'verified')
                            <span class="badge bg-success">VERIFIED</span>
                        @else
                            <span class="badge bg-danger">FAILED</span>
                        @endif
                        @if ($action->verified_at)
                            <small class="text-muted">{{ \Carbon\Carbon::parse($action->verified_at)->format('Y-m-d H:i') }}</small>
                        @endif
                    </p>
                @endif
                <a href="{{ route('records.disposal.verify', $action->id) }}" class="btn btn-outline-primary btn-sm">
                    {{ $action->verification_status ? 'Re-verify Destruction (DoD 5015.2)' : 'Verify Destruction (DoD 5015.2)' }}
                </a>
            </div>
        </div>
    @endif

    {{-- Timeline --}}
    @if (count($timeline) > 0)
        <div class="card mb-3">
            <div class="card-header"><strong>Timeline</strong></div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @foreach ($timeline as $entry)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge bg-{{ $entry->type === 'workflow' ? 'info' : 'secondary' }}">{{ ucfirst($entry->type) }}</span>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $entry->action)) }}</strong>
                                    @if ($entry->description)
                                        &mdash; {{ $entry->description }}
                                    @endif
                                </div>
                                <div class="text-muted small">
                                    {{ $entry->username ?? $entry->performer_name ?? '' }}
                                    @if ($entry->timestamp)
                                        &middot; {{ \Carbon\Carbon::parse($entry->timestamp)->format('Y-m-d H:i') }}
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('records.disposal.reject', $action->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Reject Disposal Action') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reject-reason" class="form-label">{{ __('Reason for Rejection') }}</label>
                        <textarea name="reason" id="reject-reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Reject') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Cancel Modal --}}
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('records.disposal.reject', $action->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Cancel Disposal Action') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cancel-reason" class="form-label">{{ __('Reason for Cancellation') }}</label>
                        <textarea name="reason" id="cancel-reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-warning">{{ __('Cancel Disposal') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
