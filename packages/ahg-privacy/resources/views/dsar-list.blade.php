@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="h2"><i class="fas fa-file-alt me-2"></i>{{ __('Data Subject Access Requests') }}</span>
        </div>
        <a href="{{ route('ahgprivacy.dsar-add') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>{{ __('New DSAR') }}
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">{{ __('All Status') }}</option>
                        <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>{{ __('Received') }}</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
                    <a href="{{ route('ahgprivacy.dsar-list', ['overdue' => 1]) }}" class="btn btn-outline-danger">
                        {{ __('Overdue') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Requestor') }}</th>
                        <th>{{ __('Received') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Assigned To') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if($dsars->isEmpty())
                    <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No DSARs found') }}</td></tr>
                    @else
                    @foreach($dsars as $dsar)
                    @php
$isOverdue = strtotime($dsar->due_date) < time() && !in_array($dsar->status, ['completed', 'rejected', 'withdrawn']);
                    $statusClasses = [
                        'received' => 'secondary',
                        'verified' => 'info',
                        'in_progress' => 'primary',
                        'pending_info' => 'warning',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'withdrawn' => 'dark'
                    ];
@endphp
                    <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                        <td>
                            <a href="{{ route('ahgprivacy.dsar-view', ['id' => $dsar->id]) }}">
                                <strong>{{ $dsar->reference_number }}</strong>
                            </a>
                        </td>
                        <td>{{ $requestTypes[$dsar->request_type] ?? $dsar->request_type }}</td>
                        <td>
                            {{ $dsar->requestor_name }}
                            @if($dsar->requestor_email)
                            <br><small class="text-muted">{{ $dsar->requestor_email }}</small>
                            @endif
                        </td>
                        <td>{{ $dsar->received_date }}</td>
                        <td>
                            {{ $dsar->due_date }}
                            @if($isOverdue)
                            <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> {{ __('Overdue') }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusClasses[$dsar->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $dsar->status)) }}
                            </span>
                        </td>
                        <td>{{ $dsar->assigned_username ?? '-' }}</td>
                        <td>
                            <a href="{{ route('ahgprivacy.dsar-view', ['id' => $dsar->id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
