{{-- Source Assessment - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Source Assessment')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li><li class="breadcrumb-item active">{{ __('Source Assessment') }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-microscope text-primary me-2"></i>{{ __('Source Assessment') }}</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Assessment Details') }}</div><div class="card-body">
    <form method="POST">@csrf <input type="hidden" name="object_id" value="{{ $objectId ?? 0 }}">
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label">{{ __('Source Type') }} <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><select name="source_type" class="form-select" required>
                <option value="">-- Select --</option><option value="primary" {{ ($assessment->source_type ?? '') === 'primary' ? 'selected' : '' }}>{{ __('Primary') }}</option><option value="secondary" {{ ($assessment->source_type ?? '') === 'secondary' ? 'selected' : '' }}>{{ __('Secondary') }}</option><option value="tertiary" {{ ($assessment->source_type ?? '') === 'tertiary' ? 'selected' : '' }}>{{ __('Tertiary') }}</option>
            </select></div>
            <div class="col-md-6"><label class="form-label">{{ __('Completeness') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select name="completeness" class="form-select">
                <option value="">-- Select --</option>@foreach(['complete','partial','fragment','missing_pages','redacted'] as $v)<option value="{{ $v }}" {{ ($assessment->completeness ?? '') === $v ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $v)) }}</option>@endforeach
            </select></div>
        </div>
        <div class="mb-3"><label class="form-label">{{ __('Provenance') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="provenance" class="form-control" rows="2">{{ e($assessment->provenance ?? '') }}</textarea></div>
        <div class="mb-3"><label class="form-label">{{ __('Authenticity Notes') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="authenticity_notes" class="form-control" rows="2">{{ e($assessment->authenticity_notes ?? '') }}</textarea></div>
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label">{{ __('Reliability (1-5)') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" name="reliability" class="form-control" min="1" max="5" value="{{ $assessment->reliability ?? '' }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Bias Assessment') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select name="bias" class="form-select">
                <option value="">-- None --</option>@foreach(['none','low','moderate','high','extreme'] as $v)<option value="{{ $v }}" {{ ($assessment->bias ?? '') === $v ? 'selected' : '' }}>{{ ucfirst($v) }}</option>@endforeach
            </select></div>
        </div>
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save Assessment') }}</button>
    </form>
</div></div>
</div><div class="col-md-4">
<div class="card"><div class="card-header"><h6 class="mb-0">{{ __('Source Info') }}</h6></div><div class="card-body small">
    <dl class="row mb-0"><dt class="col-5">{{ __('Title') }}</dt><dd class="col-7">{{ e($source->title ?? 'N/A') }}</dd><dt class="col-5">{{ __('Repository') }}</dt><dd class="col-7">{{ e($source->repository ?? 'N/A') }}</dd><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ e($source->date ?? 'N/A') }}</dd></dl>
</div></div>
</div></div>
@endsection