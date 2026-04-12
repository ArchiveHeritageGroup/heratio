@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-edit me-2"></i>{{ __('Edit Breach') }}
            <small class="text-muted">{{ $breach->reference_number }}</small>
        </h1>
        <div>
            <a href="{{ route('ahgprivacy.breach-view', ['id' => $breach->id]) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to View') }}
            </a>
        </div>
    </div>

    <form method="post" action="{{ route('ahgprivacy.breach-edit', ['id' => $breach->id]) }}">
        <input type="hidden" name="id" value="{{ $breach->id }}">
        
        <div class="row">
            <!-- Left Column - Main Details -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Breach Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select class="form-select" disabled>
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ ($breach->jurisdiction ?? '') === $code ? 'selected' : '' }}>{{ $info['name'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ __('Cannot change jurisdiction after creation') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Breach Type') }}</label>
                                <select name="breach_type" class="form-select">
                                    @foreach($breachTypes as $key => $label)
                                    <option value="{{ $key }}" {{ ($breach->breach_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Severity') }}</label>
                                <select name="severity" class="form-select">
                                    @foreach($severityLevels as $key => $label)
                                    <option value="{{ $key }}" {{ ($breach->severity ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Risk to Rights') }}</label>
                                <select name="risk_to_rights" class="form-select">
                                    @foreach($riskLevels as $key => $label)
                                    <option value="{{ $key }}" {{ ($breach->risk_to_rights ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Data Subjects Affected') }}</label>
                                <input type="number" name="data_subjects_affected" class="form-control" min="0" value="{{ $breach->data_subjects_affected ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Assigned To') }}</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">{{ __('-- Unassigned --') }}</option>
                                    @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ ($breach->assigned_to ?? '') == $user->id ? 'selected' : '' }}>{{ $user->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Data Categories Affected') }}</label>
                            <textarea name="data_categories_affected" class="form-control" rows="2" placeholder="{{ __('e.g., Names, ID numbers, financial data...') }}">{{ $breach->data_categories_affected ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Description & Analysis') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Title') }}</label>
                            <input type="text" name="title" class="form-control" value="{{ $breachI18n->title ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="3">{{ $breachI18n->description ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Cause') }}</label>
                            <textarea name="cause" class="form-control" rows="2">{{ $breachI18n->cause ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Impact Assessment') }}</label>
                            <textarea name="impact_assessment" class="form-control" rows="3" placeholder="{{ __('Assess the potential consequences for data subjects...') }}">{{ $breachI18n->impact_assessment ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Remedial Actions') }}</label>
                            <textarea name="remedial_actions" class="form-control" rows="3" placeholder="{{ __('Actions taken or planned to address the breach...') }}">{{ $breachI18n->remedial_actions ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Lessons Learned') }}</label>
                            <textarea name="lessons_learned" class="form-control" rows="2" placeholder="{{ __('What can be improved to prevent future breaches...') }}">{{ $breachI18n->lessons_learned ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Status & Notifications -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>{{ __('Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ ($breach->status ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>{{ __('Timeline') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Occurred Date') }}</label>
                            <input type="datetime-local" name="occurred_date" class="form-control" value="{{ $breach->occurred_date ? date('Y-m-d\TH:i', strtotime($breach->occurred_date)) : '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Detected Date') }}</label>
                            <input type="datetime-local" class="form-control" value="{{ $breach->detected_date ? date('Y-m-d\TH:i', strtotime($breach->detected_date)) : '' }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Contained Date') }}</label>
                            <input type="datetime-local" name="contained_date" class="form-control" value="{{ $breach->contained_date ? date('Y-m-d\TH:i', strtotime($breach->contained_date)) : '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Resolved Date') }}</label>
                            <input type="datetime-local" name="resolved_date" class="form-control" value="{{ $breach->resolved_date ? date('Y-m-d\TH:i', strtotime($breach->resolved_date)) : '' }}">
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>{{ __('Notifications') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="notification_required" value="1" class="form-check-input" id="notifRequired" {{ ($breach->notification_required ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="notifRequired">{{ __('Notification Required') }}</label>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="regulator_notified" value="1" class="form-check-input" id="regulatorNotified" {{ ($breach->regulator_notified ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="regulatorNotified">{{ __('Regulator Notified') }}</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Regulator Notified Date') }}</label>
                            <input type="datetime-local" name="regulator_notified_date" class="form-control" value="{{ $breach->regulator_notified_date ? date('Y-m-d\TH:i', strtotime($breach->regulator_notified_date)) : '' }}">
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="subjects_notified" value="1" class="form-check-input" id="subjectsNotified" {{ ($breach->subjects_notified ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="subjectsNotified">{{ __('Data Subjects Notified') }}</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Subjects Notified Date') }}</label>
                            <input type="datetime-local" name="subjects_notified_date" class="form-control" value="{{ $breach->subjects_notified_date ? date('Y-m-d\TH:i', strtotime($breach->subjects_notified_date)) : '' }}">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                            </button>
                            <a href="{{ route('ahgprivacy.breach-view', ['id' => $breach->id]) }}" class="btn btn-outline-secondary">
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
