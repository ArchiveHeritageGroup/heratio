@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('ahgprivacy.dsar-list') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h2 mb-0">{{ $dsar->reference_number }}</h1>
                <small class="text-muted">{{ $jurisdictionInfo['name'] ?? $dsar->jurisdiction }} - {{ $requestTypes[$dsar->request_type] ?? $dsar->request_type }}</small>
            </div>
        </div>
        <div>
            @php
$statusClasses = [
                'received' => 'secondary', 'verified' => 'info', 'in_progress' => 'primary',
                'pending_info' => 'warning', 'completed' => 'success', 'rejected' => 'danger', 'withdrawn' => 'dark'
            ];
            $isOverdue = strtotime($dsar->due_date) < time() && !in_array($dsar->status, ['completed', 'rejected', 'withdrawn']);
@endphp
            <span class="badge bg-{{ $statusClasses[$dsar->status] ?? 'secondary' }} fs-6">
                {{ ucfirst(str_replace('_', ' ', $dsar->status)) }}
            </span>
            @if($isOverdue)
            <span class="badge bg-danger fs-6 ms-1"><i class="fas fa-exclamation-triangle"></i> {{ __('Overdue') }}</span>
            @endif
            <a href="{{ route('ahgprivacy.dsar-edit', ['id' => $dsar->id]) }}" class="btn btn-primary ms-3">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Request Details -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0">{{ __('Request Details') }}</h5>
                    <span class="badge bg-{{ $dsar->priority === 'urgent' ? 'danger' : ($dsar->priority === 'high' ? 'warning' : 'secondary') }}">
                        {{ ucfirst($dsar->priority) }} {{ __('Priority') }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ __('Request Type:') }}</strong><br>{{ $requestTypes[$dsar->request_type] ?? $dsar->request_type }}</p>
                            <p><strong>{{ __('Received:') }}</strong><br>{{ $dsar->received_date }}</p>
                            <p><strong>{{ __('Due Date:') }}</strong><br>
                                <span class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">{{ $dsar->due_date }}</span>
                                @if(!$isOverdue && strtotime($dsar->due_date) > time())
                                <br><small class="text-muted">{{ ceil((strtotime($dsar->due_date) - time()) / 86400) }} {{ __('days remaining') }}</small>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ __('Assigned To:') }}</strong><br>{{ $dsar->assigned_username ?? '-' }}</p>
                            @if($dsar->completed_date)
                            <p><strong>{{ __('Completed:') }}</strong><br>{{ $dsar->completed_date }}</p>
                            @endif
                            @if($dsar->outcome)
                            <p><strong>{{ __('Outcome:') }}</strong><br>{{ ucfirst(str_replace('_', ' ', $dsar->outcome)) }}</p>
                            @endif
                        </div>
                    </div>
                    @if($dsar->description)
                    <hr>
                    <p><strong>{{ __('Description:') }}</strong></p>
                    <p>{!! nl2br(e($dsar->description)) !!}</p>
                    @endif
                </div>
            </div>

            <!-- Requestor Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Requestor') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ __('Name:') }}</strong><br>{{ $dsar->requestor_name }}</p>
                            @if($dsar->requestor_email)
                            <p><strong>{{ __('Email:') }}</strong><br><a href="mailto:{{ $dsar->requestor_email }}">{{ $dsar->requestor_email }}</a></p>
                            @endif
                            @if($dsar->requestor_phone)
                            <p><strong>{{ __('Phone:') }}</strong><br>{{ $dsar->requestor_phone }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($dsar->requestor_id_type)
                            <p><strong>{{ __('ID Type:') }}</strong><br>{{ $dsar->requestor_id_type }}</p>
                            @endif
                            @if($dsar->requestor_id_number)
                            <p><strong>{{ __('ID Number:') }}</strong><br>{{ $dsar->requestor_id_number }}</p>
                            @endif
                            <p><strong>{{ __('Verified:') }}</strong><br>
                                @if($dsar->is_verified)
                                <span class="text-success"><i class="fas fa-check-circle"></i> {{ __('Yes') }} ({{ $dsar->verified_at }})</span>
                                @else
                                <span class="text-warning"><i class="fas fa-clock"></i> {{ __('Pending') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Activity Log') }}</h5>
                </div>
                <div class="card-body p-0">
                    @if($logs->isEmpty())
                    <p class="text-muted text-center py-4">{{ __('No activity logged yet') }}</p>
                    @else
                    <ul class="list-group list-group-flush">
                        @foreach($logs as $log)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</strong>
                                    @if($log->username)
                                    <span class="text-muted">by {{ $log->username }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $log->created_at }}</small>
                            </div>
                            @if($log->details)
                            <small class="text-muted">{{ $log->details }}</small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Actions') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('ahgprivacy.dsar-update', ['id' => $dsar->id]) }}">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Update Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="received" {{ $dsar->status === 'received' ? 'selected' : '' }}>{{ __('Received') }}</option>
                                <option value="verified" {{ $dsar->status === 'verified' ? 'selected' : '' }}>{{ __('Verified') }}</option>
                                <option value="in_progress" {{ $dsar->status === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                                <option value="pending_info" {{ $dsar->status === 'pending_info' ? 'selected' : '' }}>{{ __('Pending Info') }}</option>
                                <option value="completed" {{ $dsar->status === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                                <option value="rejected" {{ $dsar->status === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                                <option value="withdrawn" {{ $dsar->status === 'withdrawn' ? 'selected' : '' }}>{{ __('Withdrawn') }}</option>
                            </select>
                        </div>

                        @if(!$dsar->is_verified)
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_verified" value="1" class="form-check-input" id="verify">
                            <label class="form-check-label" for="verify">{{ __('Mark identity as verified') }}</label>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">{{ __('Response Summary') }}</label>
                            <textarea name="response_summary" class="form-control" rows="3">{{ $dsar->response_summary ?? '' }}</textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>{{ __('Update') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Jurisdiction Info -->
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0">{{ $jurisdictionInfo['name'] ?? $dsar->jurisdiction }}</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-2">{{ $jurisdictionInfo['full_name'] ?? '' }}</p>
                    <ul class="list-unstyled small mb-0">
                        <li><i class="fas fa-clock me-2"></i>{{ __('Response deadline:') }} {{ $jurisdictionInfo['dsar_days'] ?? 30 }} {{ __('days') }}</li>
                        <li><i class="fas fa-university me-2"></i><a href="{{ $jurisdictionInfo['regulator_url'] ?? '#' }}" target="_blank">{{ $jurisdictionInfo['regulator'] ?? '' }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
