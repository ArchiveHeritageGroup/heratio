{{-- Contradiction Engine report - Research OS moonshot 17 (heratio#1236) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Contradiction Engine'))

@php
    $kinds          = $kinds ?? [];
    $severityBadges = $severityBadges ?? [];
    $statusBadges   = $statusBadges ?? [];
    $findings       = is_array($findings) ? $findings : [];
    $counts         = $statusCounts ?? ['open' => 0, 'dismissed' => 0, 'resolved' => 0];
    $statusFilter   = $statusFilter ?? 'open';
    $projectId      = $project->id ?? 0;

    $kindIcon = [
        'opposing_status'        => 'fa-arrows-left-right',
        'shared_source_conflict' => 'fa-link-slash',
        'confidence_drop'        => 'fa-arrow-trend-down',
        'definition_drift'       => 'fa-spell-check',
        'ai_flagged'             => 'fa-robot',
    ];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $projectId) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Contradiction Engine') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="fas fa-scale-unbalanced-flip text-danger me-2"></i>{{ __('Contradiction Engine') }}</h1>
    <div class="d-flex gap-2 flex-wrap">
        <form method="POST" action="{{ route('research.contradictions.scan', $projectId) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass me-1"></i>{{ __('Run scan') }}</button>
        </form>
        @if(!empty($aiAvailable))
            <form method="POST" action="{{ route('research.contradictions.aiScan', $projectId) }}" class="d-inline"
                  onsubmit="return confirm('{{ __('Send this project\'s claims to the AHG AI gateway for an additional contradiction pass?') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ __('Optional. Runs through the AHG AI gateway. Findings are labelled AI.') }}">
                    <i class="fas fa-robot me-1"></i>{{ __('AI deepen (gateway)') }}
                </button>
            </form>
        @endif
        <a href="{{ route('research.viewProject', $projectId) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Scans this project\'s claim ledger for contradictions no one is holding in working memory: claims that disagree, a single source pulled both ways, claims that have weakened, and terms used two different ways.') }}</p>

{{-- Status filter pills --}}
<ul class="nav nav-pills mb-3 small">
    @php
        $filters = [
            'open'      => __('Open').' ('.($counts['open'] ?? 0).')',
            'resolved'  => __('Resolved').' ('.($counts['resolved'] ?? 0).')',
            'dismissed' => __('Dismissed').' ('.($counts['dismissed'] ?? 0).')',
            'all'       => __('All'),
        ];
    @endphp
    @foreach($filters as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $statusFilter === $key ? 'active' : '' }}"
               href="{{ route('research.contradictions.index', $projectId) }}?status={{ $key }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if(count($findings) === 0)
    {{-- Empty state - never a 500 --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-circle-check text-success fa-2x mb-3"></i>
            <h2 class="h5">{{ __('No contradictions detected') }}</h2>
            <p class="text-muted mb-3">
                @if(($counts['open'] ?? 0) + ($counts['resolved'] ?? 0) + ($counts['dismissed'] ?? 0) === 0)
                    {{ __('Nothing has been scanned yet, or the claim ledger is internally consistent. Run a scan to check.') }}
                @else
                    {{ __('No contradictions match this filter.') }}
                @endif
            </p>
            <form method="POST" action="{{ route('research.contradictions.scan', $projectId) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass me-1"></i>{{ __('Run scan') }}</button>
            </form>
        </div>
    </div>
@else
    <div class="vstack gap-3">
        @foreach($findings as $f)
            @php
                $sev    = $f->severity ?? 'medium';
                $sevB   = $severityBadges[$sev] ?? 'secondary';
                $stB    = $statusBadges[$f->status ?? 'open'] ?? 'secondary';
                $kLabel = $kinds[$f->kind ?? ''] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $f->kind ?? ''));
                $kIcon  = $kindIcon[$f->kind ?? ''] ?? 'fa-triangle-exclamation';
                $a      = $f->claim_a ?? null;
                $b      = $f->claim_b ?? null;
            @endphp
            <div class="card shadow-sm border-start border-4 border-{{ $sevB }}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>
                        <i class="fas {{ $kIcon }} me-1 text-{{ $sevB }}"></i>
                        <strong>{{ $kLabel }}</strong>
                        <span class="badge bg-{{ $sevB }} ms-2 text-uppercase">{{ __(ucfirst($sev)) }}</span>
                        <span class="badge bg-{{ $stB }} ms-1 text-uppercase">{{ __(ucfirst($f->status ?? 'open')) }}</span>
                        @if(($f->source ?? 'heuristic') === 'ai')
                            <span class="badge bg-info text-dark ms-1"><i class="fas fa-robot me-1"></i>{{ __('AI - via gateway') }}</span>
                        @endif
                    </span>
                </div>
                <div class="card-body">
                    @if(!empty($f->detail))
                        <p class="mb-3">{{ $f->detail }}</p>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100 bg-white">
                                <div class="text-muted small text-uppercase mb-1">{{ __('Claim A') }}</div>
                                @if($a)
                                    <a href="{{ route('research.claims.show', [$projectId, $a->id]) }}">{{ e(\Illuminate\Support\Str::limit($a->label ?? __('Untitled claim'), 200)) }}</a>
                                    <div class="small text-muted mt-1">{{ __('Status') }}: {{ e(str_replace('_', ' ', (string)($a->status ?? '-'))) }}</div>
                                @else
                                    <span class="text-muted small">{{ __('Claim') }} #{{ $f->claim_a_id }} <em>({{ __('not found') }})</em></span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100 bg-white">
                                <div class="text-muted small text-uppercase mb-1">{{ __('Claim B') }}</div>
                                @if($b)
                                    <a href="{{ route('research.claims.show', [$projectId, $b->id]) }}">{{ e(\Illuminate\Support\Str::limit($b->label ?? __('Untitled claim'), 200)) }}</a>
                                    <div class="small text-muted mt-1">{{ __('Status') }}: {{ e(str_replace('_', ' ', (string)($b->status ?? '-'))) }}</div>
                                @elseif($f->claim_b_id !== null)
                                    <span class="text-muted small">{{ __('Claim') }} #{{ $f->claim_b_id }} <em>({{ __('not found') }})</em></span>
                                @else
                                    <span class="text-muted small fst-italic">{{ __('Single-claim finding (no second claim)') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    @if(($f->status ?? 'open') !== 'resolved')
                        <form method="POST" action="{{ route('research.contradictions.resolve', [$projectId, $f->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-check me-1"></i>{{ __('Resolve') }}</button>
                        </form>
                    @endif
                    @if(($f->status ?? 'open') !== 'dismissed')
                        <form method="POST" action="{{ route('research.contradictions.dismiss', [$projectId, $f->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-xmark me-1"></i>{{ __('Dismiss') }}</button>
                        </form>
                    @endif
                    @if(($f->status ?? 'open') !== 'open')
                        <form method="POST" action="{{ route('research.contradictions.reopen', [$projectId, $f->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-rotate-left me-1"></i>{{ __('Reopen') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
