{{-- Research Team & Collaborators register - create / edit form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@php $isEdit = ! empty($member); @endphp

@section('title', $isEdit ? __('Edit Team Member') : __('Add Team Member'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.team.index', $project->id ?? 0) }}">{{ __('Research Team') }}</a></li>
        <li class="breadcrumb-item active">{{ $isEdit ? __('Edit') : __('New') }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-users text-primary me-2"></i>{{ $isEdit ? __('Edit Team Member') : __('Add Team Member') }}</h1>
    <a href="{{ route('research.team.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $isEdit ? route('research.team.update', [$project->id ?? 0, $member['id']]) : route('research.team.store', $project->id ?? 0) }}">
    @csrf
    @if($isEdit)@method('PUT')@endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Person') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="person_name" class="form-control" maxlength="512" required value="{{ old('person_name', $member['person_name'] ?? '') }}" placeholder="{{ __('full name of the contributor') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Role') }} <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        @foreach($roleOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('role', $member['role'] ?? 'researcher') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Informed by the international CRediT contributor-roles taxonomy.') }}</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Affiliation') }}</label>
                    <input type="text" name="affiliation" class="form-control" maxlength="512" value="{{ old('affiliation', $member['affiliation'] ?? '') }}" placeholder="{{ __('institution or organisation') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" maxlength="255" value="{{ old('email', $member['email'] ?? '') }}" placeholder="{{ __('contact email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('ORCID iD') }}</label>
                    <input type="text" name="orcid" class="form-control" maxlength="64" value="{{ old('orcid', $member['orcid'] ?? '') }}" placeholder="0000-0000-0000-0000">
                    <div class="form-text">{{ __('An international persistent identifier for a researcher. Enter the iD only (the last character may be X); it is stored as the bare iD and shown as a link to orcid.org. Leave blank if unknown - it is never looked up online.') }}</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_lead" value="0">
                        <input type="checkbox" name="is_lead" value="1" class="form-check-input" id="is_lead" @checked(old('is_lead', ($member['is_lead'] ?? false)))>
                        <label class="form-check-label" for="is_lead">{{ __('Project lead') }}</label>
                        <div class="form-text">{{ __('Highlights this person as a lead (e.g. principal investigator) in the team summary.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Involvement') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $member['status'] ?? 'active') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Joined') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $member['start_date'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Left') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $member['end_date'] ?? '') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Contribution') }}</h6></div>
        <div class="card-body">
            <label class="form-label">{{ __('Contribution note') }}</label>
            <textarea name="contribution_note" class="form-control" rows="4" maxlength="65000" placeholder="{{ __('what this person contributed - e.g. conceptualisation, methodology, investigation, writing, supervision') }}">{{ old('contribution_note', $member['contribution_note'] ?? '') }}</textarea>
            <div class="form-text">{{ __('Free text. The international CRediT taxonomy (conceptualisation, methodology, software, validation, investigation, writing, supervision, and so on) is a recognised reference if you want to describe contributions in standard terms.') }}</div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save member') : __('Add member') }}</button>
        <a href="{{ route('research.team.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
