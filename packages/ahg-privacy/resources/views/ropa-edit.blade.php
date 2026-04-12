@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.ropa-list') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0"><i class="fas fa-clipboard-list me-2"></i>{{ __('Edit Processing Activity') }}</h1>
    </div>

    <form method="post" action="{{ route('ahgprivacy.ropa-edit', ['id' => $activity->id]) }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Basic Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Activity Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required value="{{ $activity->name ?? '' }}" placeholder="{{ __('e.g., Customer Database Processing') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select name="jurisdiction" class="form-select">
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ $code === ($activity->jurisdiction ?? $defaultJurisdiction) ? 'selected' : '' }}>
                                        {{ $info['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Purpose of Processing') }} <span class="text-danger">*</span></label>
                            <textarea name="purpose" class="form-control" rows="3" required placeholder="{{ __('Describe why this personal data is processed...') }}"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Lawful Basis') }} <span class="text-danger">*</span></label>
                                <select name="lawful_basis" class="form-select" required>
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach($lawfulBases as $key => $info)
                                    <option value="{{ $key }}" {{ $key === ($activity->lawful_basis ?? '') ? 'selected' : '' }}>{{ $info['label'] }} ({{ $info['code'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Department') }}</label>
                                <input type="text" name="department" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Data Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Categories of Personal Data') }}</label>
                            <input type="text" name="data_categories" class="form-control" placeholder="{{ __('e.g., Name, Email, ID Number, Health Data') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Categories of Data Subjects') }}</label>
                            <input type="text" name="data_subjects" class="form-control" placeholder="{{ __('e.g., Customers, Employees, Researchers') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Recipients') }}</label>
                            <textarea name="recipients" class="form-control" rows="2" placeholder="{{ __('Who receives this data? Internal departments, third parties...') }}"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Third Country Transfers') }}</label>
                                <input type="text" name="third_countries" class="form-control" placeholder="{{ __('Countries outside jurisdiction') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Transfer Safeguards') }}</label>
                                <input type="text" name="cross_border_safeguards" class="form-control" placeholder="{{ __('e.g., SCCs, BCRs') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Retention Period') }}</label>
                                <input type="text" name="retention_period" class="form-control" value="{{ $activity->retention_period ?? '' }}" placeholder="{{ __('e.g., 5 years after contract ends') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Security Measures') }}</label>
                                <input type="text" name="security_measures" class="form-control" placeholder="{{ __('e.g., Encryption, Access controls') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('DPIA (Data Protection Impact Assessment)') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="dpia_required" value="1" class="form-check-input" id="dpia_required" {{ ($activity->dpia_required ?? 0) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dpia_required">{{ __('DPIA Required') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="dpia_completed" value="1" class="form-check-input" id="dpia_completed" {{ ($activity->dpia_completed ?? 0) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dpia_completed">{{ __('DPIA Completed') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('DPIA Date') }}</label>
                                <input type="date" name="dpia_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Status & Review') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="draft" {{ ($activity->status ?? 'draft') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                <option value="pending_review" {{ ($activity->status ?? '') === 'pending_review' ? 'selected' : '' }}>{{ __('Pending Review') }}</option>
                                <option value="approved" {{ ($activity->status ?? '') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Responsible Person') }}</label>
                            <input type="text" name="responsible_person" class="form-control" value="{{ $activity->owner ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Assigned Privacy Officer') }}</label>
                            <select name="assigned_officer_id" class="form-select">
                                <option value="">{{ __('-- Select Officer --') }}</option>
                                @foreach($officers ?? [] as $officer)
                                <option value="{{ $officer->id }}" {{ ($activity->assigned_officer_id ?? '') == $officer->id ? 'selected' : '' }}>{{ $officer->name }} ({{ $officer->jurisdiction }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Next Review Date') }}</label>
                            <input type="date" name="next_review_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Save Activity') }}
                            </button>
                            <a href="{{ route('ahgprivacy.ropa-list') }}" class="btn btn-outline-secondary">
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
