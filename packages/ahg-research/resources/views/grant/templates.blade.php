{{-- Grant Engine - funder template gallery (heratio#1239) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Grant Funder Templates'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        @if(!empty($project))
            <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('research.grant.index', $project->id ?? 0) }}">{{ __('Grant Engine') }}</a></li>
        @endif
        <li class="breadcrumb-item active">{{ __('Funder templates') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-hand-holding-usd text-primary me-2"></i>{{ __('Grant Funder Templates') }}</h1>
    @if(!empty($project))
        <a href="{{ route('research.grant.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to drafts') }}</a>
    @endif
</div>

<p class="text-muted">{{ __('A funder template provides the section structure for a grant draft. The funders shown are selectable examples - your own funder templates can be added in the Dropdown Manager. Defaults are jurisdiction-neutral.') }}</p>

@if(empty($templates))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No funder templates are available yet. They are seeded automatically on first boot, or can be added from the Dropdown Manager.') }}</div>
@else
<div class="row g-3">
    @foreach($templates as $t)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">{{ e($t['name']) }}</h5>
                @if(!empty($t['funder']))
                    <p class="text-muted small mb-2">{{ e($t['funder']) }}</p>
                @endif
                <p class="small text-muted mb-2">{{ count($t['sections']) }} {{ __('sections') }}</p>
                <ul class="small text-muted ps-3 mb-3">
                    @foreach(array_slice($t['sections'], 0, 6) as $s)
                        <li>{{ e($s['label']) }}</li>
                    @endforeach
                    @if(count($t['sections']) > 6)
                        <li class="text-muted">{{ __('and :n more', ['n' => count($t['sections']) - 6]) }}</li>
                    @endif
                </ul>
                @if(!empty($project))
                <form method="POST" action="{{ route('research.grant.store', $project->id ?? 0) }}" class="mt-auto">
                    @csrf
                    <input type="hidden" name="funder_template" value="{{ e($t['code']) }}">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>{{ __('Start a draft with this template') }}</button>
                </form>
                @else
                    <span class="badge bg-light text-dark border mt-auto align-self-start">{{ e($t['code']) }}</span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
