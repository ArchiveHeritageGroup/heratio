{{-- Grant Engine - assembled draft, read-only / print view (heratio#1239) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Grant Draft'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="d-print-none">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.grant.index', $project->id ?? 0) }}">{{ __('Grant Engine') }}</a></li>
        <li class="breadcrumb-item active">{{ e($draft['title'] ?? '') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4 d-print-none">
    <div>
        <h1 class="h2 mb-1">{{ e($draft['title'] ?? '') }}</h1>
        @if(!empty($template))
            <span class="badge bg-light text-dark border">{{ e($template['name']) }}</span>
            @if(!empty($template['funder']))<span class="text-muted small ms-1">{{ e($template['funder']) }}</span>@endif
        @endif
        <span class="badge bg-secondary ms-1">{{ e(ucfirst(str_replace('_',' ', $draft['status'] ?? 'draft'))) }}</span>
        @if(!empty($draft['ai_at']))
            <div class="mt-2">
                @include('research::research._ai-decision', ['slice' => 'grant', 'id' => $draft['id'], 'decision' => $draft['ai_decision'] ?? null])
            </div>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.grant.edit', [$project->id ?? 0, $draft['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
        <a href="{{ route('research.grant.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="d-none d-print-block mb-3">
    <h1 class="h3">{{ e($draft['title'] ?? '') }}</h1>
    @if(!empty($template['funder']))<p class="text-muted">{{ e($template['funder']) }}</p>@endif
</div>

@if(empty($sections))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('This draft has no sections yet.') }}</div>
@else
    @foreach($sections as $s)
    <section class="mb-4">
        <h2 class="h5 border-bottom pb-2">{{ e($s['label']) }}</h2>
        @if(trim($s['body'] ?? '') !== '')
            <div style="white-space: pre-wrap;">{{ $s['body'] }}</div>
        @else
            <p class="text-muted fst-italic d-print-none">{{ __('Not written yet.') }}</p>
        @endif
    </section>
    @endforeach
@endif
@endsection
