{{-- Data Management Plan (DMP) Builder - section-by-section editor (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Edit Data Management Plan'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.dmp.index', $project->id ?? 0) }}">{{ __('Data Management Plans') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Edit') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-database text-primary me-2"></i>{{ e($plan['title']) }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.dmp.show', [$project->id ?? 0, $plan['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
        <a href="{{ route('research.dmp.export', [$project->id ?? 0, $plan['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('maDMP JSON') }}</a>
        <a href="{{ route('research.dmp.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

{{-- Completeness indicator --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ __('Completeness') }}</strong>
            <span class="text-muted small">{{ $completeness['filled'] }}/{{ $completeness['total'] }} {{ __('sections answered') }}</span>
        </div>
        <div class="progress" role="progressbar" aria-valuenow="{{ $completeness['pct'] }}" aria-valuemin="0" aria-valuemax="100" style="height:22px;">
            <div class="progress-bar @if($completeness['pct']==100) bg-success @endif" style="width: {{ $completeness['pct'] }}%;">{{ $completeness['pct'] }}%</div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('research.dmp.update', [$project->id ?? 0, $plan['id']]) }}">
    @csrf
    @method('PUT')

    {{-- Plan metadata --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Plan details') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="255" required value="{{ old('title', $plan['title']) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $plan['status']) === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Language') }}</label>
                    <input type="text" name="language" class="form-control" maxlength="12" value="{{ old('language', $plan['language']) }}" placeholder="en">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Funder') }}</label>
                    <input type="text" name="funder" class="form-control" maxlength="255" value="{{ old('funder', $plan['funder']) }}" placeholder="{{ __('Recorded as data - never assumed') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Funder template (optional)') }}</label>
                    <select name="funder_template" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach($funderOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('funder_template', $plan['funder_template']) === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact name') }}</label>
                    <input type="text" name="contact_name" class="form-control" maxlength="255" value="{{ old('contact_name', $plan['contact_name']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact email') }}</label>
                    <input type="email" name="contact_email" class="form-control" maxlength="255" value="{{ old('contact_email', $plan['contact_email']) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- maDMP sections --}}
    @if(empty($sections))
        <div class="alert alert-warning">{{ __('This plan has no sections. The maDMP section set could not be loaded.') }}</div>
    @else
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('maDMP sections') }}</h6></div>
        <div class="card-body">
            @foreach($sections as $s)
            <div class="mb-4">
                <label class="form-label fw-bold" for="sec_{{ $s['id'] }}">{{ e($s['label']) }}</label>
                <textarea id="sec_{{ $s['id'] }}" name="sections[{{ $s['id'] }}]" class="form-control" rows="4" maxlength="65000">{{ old('sections.'.$s['id'], $s['body']) }}</textarea>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save plan') }}</button>
        <a href="{{ route('research.dmp.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
