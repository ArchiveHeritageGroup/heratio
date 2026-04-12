@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-plus-circle me-2"></i>{{ __('Log Complaint') }}</h1>
        <a href="{{ route('ahgprivacy.complaint-list') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
    <form method="post" action="{{ route('ahgprivacy.complaint-add') }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Complainant Information') }}</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="complainant_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="complainant_email" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="tel" name="complainant_phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select name="jurisdiction" class="form-select">
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ ($defaultJurisdiction ?? '') === $code ? 'selected' : '' }}>{{ $info['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Complaint Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Complaint Type') }} <span class="text-danger">*</span></label>
                                <select name="complaint_type" class="form-select" required>
                                    <option value="">{{ __('-- Select --') }}</option>
                                    @foreach($complaintTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Date of Incident') }}</label>
                                <input type="date" name="date_of_incident" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="{{ __('Detailed description of the complaint...') }}"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Assigned To') }}</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">{{ __('-- Unassigned --') }}</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->username }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Log Complaint') }}</button>
                            <a href="{{ route('ahgprivacy.complaint-list') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
