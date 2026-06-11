{{-- Method Design Studio - discipline template gallery (heratio#1231) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Method Templates'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        @if(!empty($project))
            <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('research.method.index', $project->id ?? 0) }}">{{ __('Method Studio') }}</a></li>
        @endif
        <li class="breadcrumb-item active">{{ __('Templates') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h2 mb-1"><i class="fas fa-drafting-compass text-primary me-2"></i>{{ __('Method Design Studio') }}</h1>
        <p class="text-muted mb-0">{{ __('Discipline templates that guide a rigorous, reusable method protocol. Pick the one closest to your approach - the guidance is jurisdiction-neutral.') }}</p>
    </div>
    @if(!empty($project))
    <a href="{{ route('research.method.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to project') }}</a>
    @endif
</div>

@if(empty($templates))
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i>{{ __('No method templates are available yet. They are seeded automatically on first boot.') }}
    </div>
@else
<div class="row">
    @foreach($templates as $t)
    <div class="col-md-6 col-xl-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-start">
                <strong>{{ e($t['name']) }}</strong>
            </div>
            <div class="card-body">
                @if(!empty($t['discipline']))
                    <span class="badge bg-light text-dark border mb-2">{{ e($t['discipline']) }}</span>
                @endif
                <p class="small text-muted mb-3">{{ e($t['description']) }}</p>
                <details class="small">
                    <summary class="text-primary" style="cursor:pointer;">{{ __('Guidance areas') }} ({{ count($t['areas']) }})</summary>
                    <ul class="mt-2 mb-0 ps-3">
                        @foreach($t['areas'] as $area)
                            <li>{{ e($area['label']) }}</li>
                        @endforeach
                    </ul>
                </details>
            </div>
            <div class="card-footer bg-white">
                @if(!empty($project))
                    <form method="POST" action="{{ route('research.method.store', $project->id ?? 0) }}">
                        @csrf
                        <input type="hidden" name="template_code" value="{{ e($t['code']) }}">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-plus me-1"></i>{{ __('Start protocol from this template') }}
                        </button>
                    </form>
                @else
                    <span class="text-muted small"><i class="fas fa-info-circle me-1"></i>{{ __('Open from a project to start a protocol.') }}</span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
