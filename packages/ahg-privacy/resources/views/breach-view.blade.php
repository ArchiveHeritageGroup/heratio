@extends('theme::layouts.1col')

@section('content')
@php
$statusClasses = [
    'detected' => 'warning',
    'investigating' => 'info',
    'contained' => 'primary',
    'resolved' => 'success',
    'closed' => 'secondary'
];
$severityClasses = [
    'low' => 'success',
    'medium' => 'warning',
    'high' => 'orange',
    'critical' => 'danger'
];
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('ahgprivacy.breach-list') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h2 mb-0">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    {{ $breach->reference_number }}
                </h1>
                <small class="text-muted">{{ __('Data Breach Record') }}</small>
            </div>
        </div>
        <div>
            <span class="badge bg-{{ $severityClasses[$breach->severity] ?? 'secondary' }} fs-6 me-2">
                {{ ucfirst($breach->severity) }}
            </span>
            <span class="badge bg-{{ $statusClasses[$breach->status] ?? 'secondary' }} fs-6">
                {{ ucfirst($breach->status) }}
            </span>
            <a href="{{ route('ahgprivacy.breach-edit', ['id' => $breach->id]) }}" class="btn btn-primary ms-3">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- Breach Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Breach Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Jurisdiction') }}</label>
                            <p class="mb-0"><strong>{{ strtoupper($breach->jurisdiction) }}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Breach Type') }}</label>
                            <p class="mb-0">{{ ucfirst($breach->breach_type) }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Detected Date') }}</label>
                            <p class="mb-0">{{ $breach->detected_date }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Occurred Date') }}</label>
                            <p class="mb-0">{{ $breach->occurred_date ?: __('Unknown') }}</p>
                        </div>
                        @if($breach->data_subjects_affected)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Data Subjects Affected') }}</label>
                            <p class="mb-0"><strong>{{ number_format($breach->data_subjects_affected) }}</strong></p>
                        </div>
                        @endif
                        @if($breach->data_categories_affected)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Data Categories Affected') }}</label>
                            <p class="mb-0">{{ $breach->data_categories_affected }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Notification Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>{{ __('Notification Status') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                @if($breach->regulator_notified)
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <strong>{{ __('Regulator Notified') }}</strong>
                                    <br><small class="text-muted">{{ $breach->regulator_notified_date }}</small>
                                </div>
                                @else
                                <i class="fas fa-times-circle text-danger fa-2x me-3"></i>
                                <div>
                                    <strong>{{ __('Regulator Not Notified') }}</strong>
                                    @if($breach->notification_required)
                                    <br><small class="text-danger">{{ __('Notification required within %hours% hours', ['%hours%' => $jurisdictionInfo['breach_hours'] ?? 72]) }}</small>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                @if($breach->subjects_notified)
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <strong>{{ __('Data Subjects Notified') }}</strong>
                                    <br><small class="text-muted">{{ $breach->subjects_notified_date }}</small>
                                </div>
                                @else
                                <i class="fas fa-times-circle text-warning fa-2x me-3"></i>
                                <div>
                                    <strong>{{ __('Data Subjects Not Notified') }}</strong>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Timeline') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @if($breach->occurred_date)
                        <li class="mb-2">
                            <i class="fas fa-circle text-danger me-2"></i>
                            <strong>{{ __('Occurred') }}:</strong> {{ $breach->occurred_date }}
                        </li>
                        @endif
                        <li class="mb-2">
                            <i class="fas fa-circle text-warning me-2"></i>
                            <strong>{{ __('Detected') }}:</strong> {{ $breach->detected_date }}
                        </li>
                        @if($breach->contained_date)
                        <li class="mb-2">
                            <i class="fas fa-circle text-primary me-2"></i>
                            <strong>{{ __('Contained') }}:</strong> {{ $breach->contained_date }}
                        </li>
                        @endif
                        @if($breach->resolved_date)
                        <li class="mb-2">
                            <i class="fas fa-circle text-success me-2"></i>
                            <strong>{{ __('Resolved') }}:</strong> {{ $breach->resolved_date }}
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Actions') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('ahgprivacy.breach-update', ['id' => $breach->id]) }}">
                        <input type="hidden" name="id" value="{{ $breach->id }}">
                        
                        <div class="mb-3">
                            <label class="form-label">{{ __('Update Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="detected" {{ $breach->status === 'detected' ? 'selected' : '' }}>{{ __('Detected') }}</option>
                                <option value="investigating" {{ $breach->status === 'investigating' ? 'selected' : '' }}>{{ __('Investigating') }}</option>
                                <option value="contained" {{ $breach->status === 'contained' ? 'selected' : '' }}>{{ __('Contained') }}</option>
                                <option value="resolved" {{ $breach->status === 'resolved' ? 'selected' : '' }}>{{ __('Resolved') }}</option>
                                <option value="closed" {{ $breach->status === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Severity') }}</label>
                            <select name="severity" class="form-select">
                                @foreach($severityLevels as $key => $label)
                                <option value="{{ $key }}" {{ $breach->severity === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <hr>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="regulator_notified" value="1" id="regulator_notified" {{ $breach->regulator_notified ? 'checked' : '' }}>
                            <label class="form-check-label" for="regulator_notified">{{ __('Regulator Notified') }}</label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="subjects_notified" value="1" id="subjects_notified" {{ $breach->subjects_notified ? 'checked' : '' }}>
                            <label class="form-check-label" for="subjects_notified">{{ __('Data Subjects Notified') }}</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i>{{ __('Update Breach') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ strtoupper($breach->jurisdiction) }} {{ __('Requirements') }}</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-2">
                        <strong>{{ __('Notification Deadline') }}:</strong><br>
                        {{ $jurisdictionInfo['breach_hours'] ?? 72 }} {{ __('hours') }}
                    </p>
                    <p class="mb-0">
                        <strong>{{ __('Regulator') }}:</strong><br>
                        {{ $jurisdictionInfo['regulator'] ?? __('Not specified') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
