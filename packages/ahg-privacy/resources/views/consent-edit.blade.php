@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-edit me-2"></i>{{ __('Edit Consent Record') }} #{{ $consent->id }}</h1>
        <a href="{{ route('ahgprivacy.consent-view', ['id' => $consent->id]) }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
    <form method="post" action="{{ route('ahgprivacy.consent-edit', ['id' => $consent->id]) }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Data Subject') }}</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Subject Identifier') }} <span class="text-danger">*</span></label>
                                <input type="text" name="data_subject_id" class="form-control" value="{{ $consent->data_subject_id }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select name="jurisdiction" class="form-select">
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ ($consent->jurisdiction ?? '') === $code ? 'selected' : '' }}>{{ $info['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Full Name') }}</label>
                                <input type="text" name="subject_name" class="form-control" value="{{ $consent->subject_name ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="subject_email" class="form-control" value="{{ $consent->subject_email ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>{{ __('Consent Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Purpose') }} <span class="text-danger">*</span></label>
                                <input type="text" name="purpose" class="form-control" value="{{ $consent->purpose }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Consent Method') }}</label>
                                <select name="consent_method" class="form-select">
                                    @foreach($consentMethods as $key => $label)
                                    <option value="{{ $key }}" {{ ($consent->consent_method ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Source') }}</label>
                                <input type="text" name="source" class="form-control" value="{{ $consent->source ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-select">
                                    <option value="active" {{ ($consent->status ?? '') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="withdrawn" {{ ($consent->status ?? '') === 'withdrawn' ? 'selected' : '' }}>{{ __('Withdrawn') }}</option>
                                    <option value="expired" {{ ($consent->status ?? '') === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="consent_given" value="1" class="form-check-input" id="consentGiven" {{ ($consent->consent_given ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="consentGiven">{{ __('Consent Given') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Changes') }}</button>
                            <a href="{{ route('ahgprivacy.consent-view', ['id' => $consent->id]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
