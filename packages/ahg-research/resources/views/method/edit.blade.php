{{-- Method Design Studio - protocol editor, guidance area by area (heratio#1231) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Edit Method Protocol'))

@section('content')
@php
    // Prefer the template's ordered guidance areas; fall back to the canonical set.
    $areas = $template['areas'] ?? [];
    if (empty($areas)) {
        foreach (\AhgResearch\Services\MethodStudioService::AREAS as $k => $label) {
            $areas[$k] = ['label' => $label, 'prompt' => '', 'placeholder' => ''];
        }
    }
    $fields = $protocol['fields'] ?? [];
    $currentStatus = $protocol['status'] ?? 'draft';
@endphp

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.method.index', $project->id ?? 0) }}">{{ __('Method Studio') }}</a></li>
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

<form method="POST" action="{{ route('research.method.update', [$project->id ?? 0, $protocol['id']]) }}" autocomplete="off">
    @csrf
    @method('PUT')

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="fas fa-drafting-compass text-primary me-2"></i>{{ __('Method Protocol') }}</h1>
            @if(!empty($template))
                <span class="badge bg-light text-dark border">{{ e($template['name']) }}</span>
                @if(!empty($template['discipline']))<span class="text-muted small ms-1">{{ e($template['discipline']) }}</span>@endif
            @endif
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('research.method.show', [$project->id ?? 0, $protocol['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
            <a href="{{ route('research.method.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Protocol title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="255" required value="{{ e($protocol['title'] ?? '') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected($currentStatus === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Guidance areas --}}
    @foreach($areas as $key => $area)
    <div class="card mb-3">
        <div class="card-header">
            <strong>{{ e($area['label'] ?? $key) }}</strong>
        </div>
        <div class="card-body">
            @if(!empty($area['prompt']))
                <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i>{{ e($area['prompt']) }}</p>
            @endif
            <textarea name="fields[{{ e($key) }}]" class="form-control" rows="4"
                placeholder="{{ e($area['placeholder'] ?? '') }}">{{ $fields[$key] ?? '' }}</textarea>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 my-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save protocol') }}</button>
        <a href="{{ route('research.method.show', [$project->id ?? 0, $protocol['id']]) }}" class="btn btn-outline-secondary"><i class="fas fa-eye me-1"></i>{{ __('View / print') }}</a>
    </div>
</form>
@endsection
