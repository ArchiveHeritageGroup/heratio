{{-- Replication Pack - per-project bundle page (heratio#1238, moonshot 22) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Replication Pack'))

@section('content')
@php
    $sections = [
        ['key' => 'method',    'icon' => 'fa-drafting-compass'],
        ['key' => 'analysis',  'icon' => 'fa-chart-column'],
        ['key' => 'decisions', 'icon' => 'fa-clipboard-list'],
        ['key' => 'claims',    'icon' => 'fa-scale-balanced'],
        ['key' => 'artifacts', 'icon' => 'fa-database'],
    ];
    $anyAvailable = collect($summary ?? [])->contains(fn ($s) => !empty($s['available']) && (int) ($s['count'] ?? 0) > 0);
@endphp

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Replication Pack') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-box-archive text-primary me-2"></i>{{ __('Replication Pack') }}</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<p class="text-muted">{{ __('One click assembles everything needed to replicate this study - the method, the analysis results and their provenance, the decision trail, and the claims with their evidence - read-only from what you have already recorded. The pack is a ZIP with a README and a manifest of what is included and what is withheld.') }}</p>

{{-- What the pack would contain --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('What this pack will contain') }}</h6></div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($sections as $sec)
                @php $s = $summary[$sec['key']] ?? ['label' => $sec['key'], 'available' => false, 'count' => 0]; @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas {{ $sec['icon'] }} text-muted me-2"></i>{{ e($s['label'] ?? '') }}
                        @if(!empty($s['links']))<span class="text-muted small ms-2">{{ trans_choice(':n claim link|:n claim links', (int) $s['links'], ['n' => (int) $s['links']]) }}</span>@endif
                        @if(!empty($s['evidence']))<span class="text-muted small ms-2">{{ trans_choice(':n evidence row|:n evidence rows', (int) $s['evidence'], ['n' => (int) $s['evidence']]) }}</span>@endif
                    </span>
                    <span>
                        @if(!empty($s['available']) && (int) ($s['count'] ?? 0) > 0)
                            <span class="badge bg-success">{{ trans_choice(':n item|:n items', (int) $s['count'], ['n' => (int) $s['count']]) }}</span>
                        @elseif(!empty($s['available']))
                            <span class="badge bg-secondary">{{ __('Nothing recorded yet') }}</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ __('Slice not installed - will be noted as omitted') }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- Ethics note --}}
<div class="alert alert-warning">
    <i class="fas fa-shield-halved me-1"></i>
    <strong>{{ __('Ethics and access:') }}</strong>
    {{ __('The pack bundles metadata, provenance and the reasoning trail. It does NOT include the underlying data files or code bytes - those are referenced by path or repository only. Restricted, embargoed or consent-limited material is intentionally withheld and listed as omitted in the manifest.') }}
</div>

{{-- Build / download --}}
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-1">{{ __('Build the replication pack') }}</h6>
            <p class="text-muted small mb-0">{{ __('Generates a ZIP you can download. The bundle is assembled fresh each time from the current records.') }}</p>
        </div>
        <form method="POST" action="{{ route('research.replication.build', $project->id ?? 0) }}" class="mb-0">
            @csrf
            <button type="submit" class="btn btn-primary" @if(!$anyAvailable) disabled @endif>
                <i class="fas fa-download me-1"></i>{{ __('Build & download') }}
            </button>
        </form>
    </div>
    @unless($anyAvailable)
        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle me-1"></i>{{ __('Nothing has been recorded yet for this project. Record a method protocol, analysis results, decisions or claims first - then build the pack.') }}
        </div>
    @endunless
</div>

{{-- Recent builds (optional audit) --}}
@if(!empty($recent))
<div class="card">
    <div class="card-header"><h6 class="mb-0">{{ __('Recent builds') }}</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>{{ __('Built at') }}</th></tr></thead>
            <tbody>
                @foreach($recent as $r)
                    <tr><td class="text-muted small">{{ e($r->built_at ?? '') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
