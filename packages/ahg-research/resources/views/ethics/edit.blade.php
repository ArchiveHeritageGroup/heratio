{{-- Research Ethics & Consent register - create / edit form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@php $isEdit = ! empty($record); @endphp

@section('title', $isEdit ? __('Edit Ethics Record') : __('New Ethics Record'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.ethics.index', $project->id ?? 0) }}">{{ __('Research Ethics & Consent') }}</a></li>
        <li class="breadcrumb-item active">{{ $isEdit ? __('Edit') : __('New') }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-shield-alt text-primary me-2"></i>{{ $isEdit ? __('Edit Ethics Record') : __('New Ethics Record') }}</h1>
    <a href="{{ route('research.ethics.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $isEdit ? route('research.ethics.update', [$project->id ?? 0, $record['id']]) : route('research.ethics.store', $project->id ?? 0) }}">
    @csrf
    @if($isEdit)@method('PUT')@endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Approval') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="512" required value="{{ old('title', $record['title'] ?? '') }}" placeholder="{{ __('e.g. Interview study - participant consent') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Approval type') }} <span class="text-danger">*</span></label>
                    <select name="approval_type" class="form-select" required>
                        @foreach($typeOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('approval_type', $record['approval_type'] ?? 'human_subjects') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Committee / review body') }}</label>
                    <input type="text" name="committee_name" class="form-control" maxlength="512" value="{{ old('committee_name', $record['committee_name'] ?? '') }}" placeholder="{{ __('name of the ethics committee or review board') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Reference number') }}</label>
                    <input type="text" name="reference_number" class="form-control" maxlength="128" value="{{ old('reference_number', $record['reference_number'] ?? '') }}" placeholder="{{ __('protocol / approval reference') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $record['status'] ?? 'pending') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Decision date') }}</label>
                    <input type="date" name="decision_date" class="form-control" value="{{ old('decision_date', $record['decision_date'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Expiry date') }}</label>
                    <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date', $record['expiry_date'] ?? '') }}">
                    <div class="form-text">{{ __('Approvals expiring within 60 days are flagged on the register.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Consent & data') }}</h6></div>
        <div class="card-body">
            <p class="text-muted small">{{ __('The consent basis records the general governance basis on which the data is held and used. These are generic, jurisdiction-neutral concepts, not the lawful-basis terms of any single regime.') }}</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Consent basis') }} <span class="text-danger">*</span></label>
                    <select name="consent_basis" class="form-select" required>
                        @foreach($consentOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('consent_basis', $record['consent_basis'] ?? 'informed_consent') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Data sensitivity') }} <span class="text-danger">*</span></label>
                    <select name="data_sensitivity" class="form-select" required>
                        @foreach($sensOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('data_sensitivity', $record['data_sensitivity'] ?? 'none') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Notes and links') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="4" maxlength="65000" placeholder="{{ __('conditions of approval, consent arrangements, or other governance notes') }}">{{ old('notes', $record['notes'] ?? '') }}</textarea>
                </div>
                @if(! empty($dmpOptions))
                <div class="col-md-6">
                    <label class="form-label">{{ __('Linked data management plan (optional)') }}</label>
                    <select name="dmp_id" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach($dmpOptions as $id => $label)
                            <option value="{{ $id }}" @selected((int) old('dmp_id', $record['dmp_id'] ?? 0) === (int) $id)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Link this record to the project data management plan that governs its data.') }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save record') : __('Create record') }}</button>
        <a href="{{ route('research.ethics.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
