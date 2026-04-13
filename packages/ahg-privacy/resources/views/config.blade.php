@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h2 mb-0"><i class="fas fa-cog me-2"></i>{{ __('Privacy Settings') }}</h1>
            <a href="{{ route('ahgprivacy.jurisdiction-list') }}" class="btn btn-outline-info">
                <i class="fas fa-globe me-1"></i>{{ __('Manage Jurisdictions') }}
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Jurisdiction Tabs -->
    <ul class="nav nav-tabs mb-4">
        @foreach($jurisdictions as $code => $info)
        <li class="nav-item">
            <a class="nav-link {{ $currentJurisdiction === $code ? 'active' : '' }}" 
               href="{{ route('ahgprivacy.config', ['jurisdiction' => $code]) }}">
                {{ $info['name'] }}
            </a>
        </li>
        @endforeach
    </ul>

    <form method="post" action="{{ route('ahgprivacy.config', ['jurisdiction' => $currentJurisdiction]) }}">
        @csrf
        <input type="hidden" name="jurisdiction" value="{{ $currentJurisdiction }}">
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ $jurisdictionInfo['name'] ?? $currentJurisdiction }} {{ __('Configuration') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Organization Name') }}</label>
                                <input type="text" name="organization_name" class="form-control" 
                                       value="{{ $config->organization_name ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Registration Number') }}</label>
                                <input type="text" name="registration_number" class="form-control" 
                                       value="{{ $config->registration_number ?? '' }}"
                                       placeholder="{{ __('e.g., Company registration') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Data Protection Email') }}</label>
                                <input type="email" name="data_protection_email" class="form-control" 
                                       value="{{ $config->data_protection_email ?? '' }}"
                                       placeholder="privacy@example.org">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('DSAR Response Days') }}</label>
                                <input type="number" name="dsar_response_days" class="form-control" 
                                       value="{{ $config->dsar_response_days ?? $jurisdictionInfo['dsar_days'] ?? 30 }}"
                                       min="1" max="90">
                                <small class="text-muted">{{ __('Default:') }} {{ $jurisdictionInfo['dsar_days'] ?? 30 }} {{ __('days') }}</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Breach Notification Hours') }}</label>
                                <input type="number" name="breach_notification_hours" class="form-control" 
                                       value="{{ $config->breach_notification_hours ?? $jurisdictionInfo['breach_hours'] ?? 72 }}"
                                       min="0" max="168">
                                <small class="text-muted">{{ __('Default:') }} {{ $jurisdictionInfo['breach_hours'] ?: 'ASAP' }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Default Retention Years') }}</label>
                                <input type="number" name="retention_default_years" class="form-control" 
                                       value="{{ $config->retention_default_years ?? 5 }}"
                                       min="1" max="100">
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                                   {{ ($config->is_active ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">{{ __('Enable this jurisdiction') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Jurisdiction Info -->
                <div class="card mb-4 bg-light">
                    <div class="card-header">
                        <h5 class="mb-0">{{ $jurisdictionInfo['name'] ?? '' }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">{{ $jurisdictionInfo['full_name'] ?? '' }}</p>
                        <ul class="list-unstyled small">
                            <li><strong>{{ __('Country:') }}</strong> {{ $jurisdictionInfo['country'] ?? '' }}</li>
                            <li><strong>{{ __('Effective:') }}</strong> {{ $jurisdictionInfo['effective_date'] ?? '' }}</li>
                            <li><strong>{{ __('Regulator:') }}</strong><br>
                                <a href="{{ $jurisdictionInfo['regulator_url'] ?? '#' }}" target="_blank">
                                    {{ $jurisdictionInfo['regulator'] ?? '' }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Assigned Officers -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Privacy Officers') }}</h5>
                        <a href="{{ route('ahgprivacy.officer-add') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                    <ul class="list-group list-group-flush">
                        @if($officers->isEmpty())
                        <li class="list-group-item text-muted">{{ __('No officers assigned') }}</li>
                        @else
                        @foreach($officers as $officer)
                        <li class="list-group-item">
                            <strong>{{ $officer->name }}</strong>
                            @if($officer->title)
                            <br><small class="text-muted">{{ $officer->title }}</small>
                            @endif
                        </li>
                        @endforeach
                        @endif
                    </ul>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>{{ __('Save Configuration') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
