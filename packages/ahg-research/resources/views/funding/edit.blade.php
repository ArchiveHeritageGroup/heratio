{{-- Research Funding tracker - create / edit form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@php $isEdit = ! empty($record); @endphp

@section('title', $isEdit ? __('Edit Funding Record') : __('New Funding Record'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.funding.index', $project->id ?? 0) }}">{{ __('Research Funding') }}</a></li>
        <li class="breadcrumb-item active">{{ $isEdit ? __('Edit') : __('New') }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-hand-holding-dollar text-primary me-2"></i>{{ $isEdit ? __('Edit Funding Record') : __('New Funding Record') }}</h1>
    <a href="{{ route('research.funding.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $isEdit ? route('research.funding.update', [$project->id ?? 0, $record['id']]) : route('research.funding.store', $project->id ?? 0) }}" autocomplete="off">
    @csrf
    @if($isEdit)@method('PUT')@endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Funder') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="512" required value="{{ old('title', $record['title'] ?? '') }}" placeholder="{{ __('e.g. Open Heritage Fellowship 2026') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Funder type') }} <span class="text-danger">*</span></label>
                    <select name="funder_type" class="form-select" required>
                        @foreach($typeOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('funder_type', $record['funder_type'] ?? 'other') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Funder name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="funder_name" class="form-control" maxlength="512" required value="{{ old('funder_name', $record['funder_name'] ?? '') }}" placeholder="{{ __('name of the funding body') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Award reference') }}</label>
                    <input type="text" name="award_reference" class="form-control" maxlength="128" value="{{ old('award_reference', $record['award_reference'] ?? '') }}" placeholder="{{ __('grant / award number') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Amount & period') }}</h6></div>
        <div class="card-body">
            <p class="text-muted small">{{ __('The amount is held as an exact decimal in the chosen currency. No currency is assumed - select the ISO 4217 code that applies. Different currencies are reported separately and never added together.') }}</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Amount') }}</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" max="999999999999.99" value="{{ old('amount', ($record['amount'] ?? '') !== '' ? $record['amount'] : '') }}" placeholder="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Currency') }} <span class="text-danger">*</span></label>
                    <select name="currency" class="form-select" required>
                        @php $curSel = old('currency', $record['currency'] ?? ''); @endphp
                        @if($curSel === '')<option value="" disabled selected>{{ __('Select a currency') }}</option>@endif
                        @foreach($currencyOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected($curSel === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $record['status'] ?? 'applied') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Award start date') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $record['start_date'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Award end date') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $record['end_date'] ?? '') }}">
                    <div class="form-text">{{ __('An award whose period brackets today (or whose status is Active) is flagged as active on the tracker.') }}</div>
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
                    <textarea name="notes" class="form-control" rows="4" maxlength="65000" placeholder="{{ __('reporting obligations, co-funding arrangements, or other notes') }}">{{ old('notes', $record['notes'] ?? '') }}</textarea>
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
                    <div class="form-text">{{ __('Link this funding line to the project data management plan whose data it supports.') }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save record') : __('Create record') }}</button>
        <a href="{{ route('research.funding.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
