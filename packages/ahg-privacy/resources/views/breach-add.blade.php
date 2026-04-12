@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.breach-list') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>{{ __('Report Data Breach') }}</h1>
    </div>

    <div class="alert alert-danger">
        <i class="fas fa-clock me-2"></i>
        <strong>{{ __('Time-sensitive:') }}</strong> {{ __('Most regulations require breach notification within 72 hours of detection.') }}
    </div>

    <form method="post" action="{{ route('ahgprivacy.breach-add') }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">{{ __('Breach Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }} <span class="text-danger">*</span></label>
                                <select name="jurisdiction" class="form-select" required>
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ $code === $defaultJurisdiction ? 'selected' : '' }}>
                                        {{ $info['name'] }} ({{ $info['country'] }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Breach Type') }} <span class="text-danger">*</span></label>
                                <select name="breach_type" class="form-select" required>
                                    @foreach($breachTypes as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Severity') }} <span class="text-danger">*</span></label>
                                <select name="severity" class="form-select" required>
                                    @foreach($severityLevels as $key => $label)
                                    <option value="{{ $key }}" {{ $key === 'medium' ? 'selected' : '' }}>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Records Affected') }}</label>
                                <input type="number" name="records_affected" class="form-control" min="0">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Detected Date/Time') }} <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="detected_date" class="form-control" value="{{ date('Y-m-d\TH:i') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Occurred Date/Time') }}</label>
                                <input type="datetime-local" name="occurred_date" class="form-control">
                                <small class="text-muted">{{ __('If known') }}</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Data Categories Affected') }}</label>
                            <input type="text" name="data_categories" class="form-control" placeholder="{{ __('e.g., Names, ID numbers, Health data') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Cause') }}</label>
                            <select name="cause" class="form-select">
                                <option value="">{{ __('Select...') }}</option>
                                <option value="cyber_attack">{{ __('Cyber Attack / Hacking') }}</option>
                                <option value="malware">{{ __('Malware / Ransomware') }}</option>
                                <option value="phishing">{{ __('Phishing') }}</option>
                                <option value="human_error">{{ __('Human Error') }}</option>
                                <option value="system_failure">{{ __('System Failure') }}</option>
                                <option value="theft">{{ __('Theft of Device/Media') }}</option>
                                <option value="unauthorized_access">{{ __('Unauthorized Access') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }} <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="{{ __('Describe what happened, what data was affected, and how the breach was detected...') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>{{ __('Notification Deadlines') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($jurisdictions as $code => $info)
                                <tr>
                                    <td>{{ $info['name'] }}</td>
                                    <td class="text-end"><strong>{{ $info['breach_hours'] ?: 'ASAP' }}{{ $info['breach_hours'] ? 'h' : '' }}</strong></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fas fa-exclamation-circle me-2"></i>{{ __('Report Breach') }}
                            </button>
                            <a href="{{ route('ahgprivacy.breach-list') }}" class="btn btn-outline-secondary">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
