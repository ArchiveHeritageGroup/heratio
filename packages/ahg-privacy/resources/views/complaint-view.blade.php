@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('ahgprivacy.complaint-list') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h2 mb-0">{{ $complaint->reference_number }}</h1>
                <small class="text-muted">{{ ucwords(str_replace('_', ' ', $complaint->complaint_type)) }}</small>
            </div>
        </div>
        @php
$statusClasses = [
            'received' => 'secondary', 'investigating' => 'primary', 
            'resolved' => 'success', 'escalated' => 'danger', 'closed' => 'dark'
        ];
@endphp
        <span class="badge bg-{{ $statusClasses[$complaint->status] ?? 'secondary' }} fs-6">
            {{ ucfirst($complaint->status) }}
        </span>
            <a href="{{ route('ahgprivacy.complaint-edit', ['id' => $complaint->id]) }}" class="btn btn-primary ms-3">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Complaint Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>{{ __('Complaint Type:') }}</strong><br>
                            {{ ucwords(str_replace('_', ' ', $complaint->complaint_type)) }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ __('Date of Incident:') }}</strong><br>
                            {{ $complaint->date_of_incident ? date('d M Y', strtotime($complaint->date_of_incident)) : '-' }}</p>
                        </div>
                    </div>
                    <p><strong>{{ __('Description:') }}</strong></p>
                    <p class="bg-light p-3 rounded">{!! nl2br(e($complaint->description)) !!}</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Complainant') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ __('Name:') }}</strong><br>{{ $complaint->complainant_name }}</p>
                            <p><strong>{{ __('Email:') }}</strong><br>
                            <a href="mailto:{{ $complaint->complainant_email }}">{{ $complaint->complainant_email }}</a></p>
                        </div>
                        <div class="col-md-6">
                            @if($complaint->complainant_phone)
                            <p><strong>{{ __('Phone:') }}</strong><br>{{ $complaint->complainant_phone }}</p>
                            @endif
                            <p><strong>{{ __('Submitted:') }}</strong><br>{{ date('d M Y H:i', strtotime($complaint->created_at)) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if($complaint->resolution)
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">{{ __('Resolution') }}</h5>
                </div>
                <div class="card-body">
                    <p>{!! nl2br(e($complaint->resolution)) !!}</p>
                    @if($complaint->resolved_date)
                    <p class="text-muted mb-0"><small>{{ __('Resolved:') }} {{ date('d M Y', strtotime($complaint->resolved_date)) }}</small></p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Update Status') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('ahgprivacy.complaint-update', ['id' => $complaint->id]) }}">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="received" {{ $complaint->status === 'received' ? 'selected' : '' }}>{{ __('Received') }}</option>
                                <option value="investigating" {{ $complaint->status === 'investigating' ? 'selected' : '' }}>{{ __('Investigating') }}</option>
                                <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>{{ __('Resolved') }}</option>
                                <option value="escalated" {{ $complaint->status === 'escalated' ? 'selected' : '' }}>{{ __('Escalated') }}</option>
                                <option value="closed" {{ $complaint->status === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Resolution Notes') }}</label>
                            <textarea name="resolution" class="form-control" rows="4">{{ $complaint->resolution ?? '' }}</textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>{{ __('Update') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
