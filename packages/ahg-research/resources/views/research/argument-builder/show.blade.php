{{-- Argument Builder - Research OS Stage 12 (heratio#1229) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Argument Builder')

@php
    $slots = $slots ?? [];
    $steps = is_array($steps) ? $steps : [];
    $warnings = is_array($warnings) ? $warnings : [];
    $warningsBySlot = is_array($warningsBySlot) ? $warningsBySlot : [];
    $availableClaims = is_array($availableClaims) ? $availableClaims : [];
    $argument = $argument ?? null;

    $severityBadge = ['danger' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $severityIcon  = ['danger' => 'fa-circle-exclamation', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];

    // Slots that have at least one step placed.
    $placedSlots = [];
    foreach ($steps as $s) { $placedSlots[$s->slot] = true; }
    $unplacedSlots = [];
    foreach ($slots as $key => $meta) {
        if (empty($placedSlots[$key])) { $unplacedSlots[$key] = $meta; }
    }
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Argument Builder') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-diagram-project text-primary me-2"></i>{{ __('Argument Builder') }}</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<p class="text-muted">{{ __('Sequence your claims into a nine-step argument, from problem to implication. The system flags weak spots so no conclusion outruns its evidence.') }}</p>

{{-- Central thesis --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="fas fa-bullseye me-1 text-primary"></i>{{ __('Central thesis') }}</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('research.argument.update', $project->id) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ __('Title') }}</label>
                <input type="text" name="title" class="form-control form-control-sm" maxlength="255"
                       value="{{ e($argument->title ?? '') }}" placeholder="{{ __('A short name for this argument') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Thesis statement') }}</label>
                <textarea name="central_thesis" class="form-control" rows="2"
                          placeholder="{{ __('State the single claim this whole argument exists to defend.') }}">{{ $argument->central_thesis ?? '' }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save thesis') }}</button>
        </form>
    </div>
</div>

<div class="row g-4">
    {{-- Argument canvas --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-list-ol me-1"></i>{{ __('Argument sequence') }}</span>
                <span class="badge bg-secondary">{{ count($steps) }} / {{ count($slots) }} {{ __('steps') }}</span>
            </div>
            <div class="card-body">
                @if(count($steps) === 0)
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-diagram-project fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">{{ __('No steps yet. Add a slot below and attach a claim from this project to start building the argument.') }}</p>
                    </div>
                @else
                    <ol class="list-unstyled mb-0">
                        @foreach($steps as $i => $s)
                            @php
                                $meta = $slots[$s->slot] ?? ['label' => ucfirst(str_replace('_',' ', $s->slot)), 'hint' => ''];
                                $claim = $s->claim ?? null;
                                $stepWarnings = $warningsBySlot[$s->slot] ?? [];
                            @endphp
                            <li class="border rounded mb-3 p-3 {{ count($stepWarnings) ? 'border-warning' : '' }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-primary me-2">{{ $i + 1 }}</span>
                                        <span class="fw-bold">{{ __($meta['label']) }}</span>
                                        <div class="small text-muted">{{ __($meta['hint']) }}</div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        {{-- Reorder up/down via single-step swap in the order list --}}
                                        @if($i > 0)
                                            <form method="POST" action="{{ route('research.argument.steps.reorder', $project->id) }}" class="d-inline">
                                                @csrf
                                                @php
                                                    $ids = collect($steps)->pluck('id')->all();
                                                    [$ids[$i-1], $ids[$i]] = [$ids[$i], $ids[$i-1]];
                                                @endphp
                                                <input type="hidden" name="order" value="{{ implode(',', $ids) }}">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit" title="{{ __('Move up') }}"><i class="fas fa-arrow-up"></i></button>
                                            </form>
                                        @endif
                                        @if($i < count($steps) - 1)
                                            <form method="POST" action="{{ route('research.argument.steps.reorder', $project->id) }}" class="d-inline">
                                                @csrf
                                                @php
                                                    $ids = collect($steps)->pluck('id')->all();
                                                    [$ids[$i+1], $ids[$i]] = [$ids[$i], $ids[$i+1]];
                                                @endphp
                                                <input type="hidden" name="order" value="{{ implode(',', $ids) }}">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit" title="{{ __('Move down') }}"><i class="fas fa-arrow-down"></i></button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('research.argument.steps.delete', [$project->id, $s->id]) }}" class="d-inline"
                                              onsubmit="return confirm('{{ __('Remove this step from the argument?') }}');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ __('Remove step') }}"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Attached claim + picker --}}
                                <form method="POST" action="{{ route('research.argument.steps.claim', [$project->id, $s->id]) }}" class="d-flex gap-2 align-items-center mb-2">
                                    @csrf
                                    <select name="assertion_id" class="form-select form-select-sm">
                                        <option value="">{{ __('-- No claim attached --') }}</option>
                                        @foreach($availableClaims as $ac)
                                            <option value="{{ $ac->id }}" {{ ($claim && $claim->id == $ac->id) ? 'selected' : '' }}>
                                                {{ e(\Illuminate\Support\Str::limit($ac->label, 90)) }} ({{ (int)($ac->evidence_count ?? 0) }} {{ __('ev.') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-link"></i></button>
                                </form>

                                @if($claim)
                                    <div class="small">
                                        <span class="badge bg-{{ in_array(strtolower((string)($claim->status ?? '')), ['supported','verified','publishable']) ? 'success' : (in_array(strtolower((string)($claim->status ?? '')), ['rejected','contested','disputed','weak']) ? 'danger' : 'secondary') }}">{{ ucfirst((string)($claim->status ?? 'idea')) }}</span>
                                        <span class="text-muted ms-1">
                                            @if(($claim->evidence_count ?? 0) > 0)
                                                {{ (int)$claim->evidence_count }} {{ __('citation(s)') }}, {{ (int)($claim->distinct_sources ?? 0) }} {{ __('source(s)') }}
                                            @else
                                                <span class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ __('no evidence') }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endif

                                {{-- Inline warnings for this slot --}}
                                @foreach($stepWarnings as $w)
                                    <div class="alert alert-{{ $severityBadge[$w['severity']] ?? 'warning' }} py-1 px-2 small mt-2 mb-0">
                                        <i class="fas {{ $severityIcon[$w['severity']] ?? 'fa-triangle-exclamation' }} me-1"></i>{{ $w['message'] }}
                                    </div>
                                @endforeach
                            </li>
                        @endforeach
                    </ol>
                @endif

                {{-- Add a step --}}
                <div class="border-top pt-3 mt-2">
                    <form method="POST" action="{{ route('research.argument.steps.add', $project->id) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small mb-1">{{ __('Slot') }}</label>
                            <select name="slot" class="form-select form-select-sm" required>
                                @foreach($slots as $key => $meta)
                                    <option value="{{ $key }}">{{ __($meta['label']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">{{ __('Claim (optional)') }}</label>
                            <select name="assertion_id" class="form-select form-select-sm">
                                <option value="">{{ __('-- Attach later --') }}</option>
                                @foreach($availableClaims as $ac)
                                    <option value="{{ $ac->id }}">{{ e(\Illuminate\Support\Str::limit($ac->label, 80)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>{{ __('Add step') }}</button>
                        </div>
                    </form>
                    @if(count($availableClaims) === 0)
                        <div class="small text-muted mt-2">
                            <i class="fas fa-info-circle me-1"></i>{{ __('No claims in this project yet.') }}
                            <a href="{{ route('research.claims.index', $project->id) }}">{{ __('Add claims in the Claim Ledger') }}</a> {{ __('first, then attach them here.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Warnings panel + missing slots --}}
    <div class="col-lg-4">
        <div class="card border-warning mb-3">
            <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-triangle-exclamation text-warning me-1"></i>{{ __('Weak-spot warnings') }}</span>
                <span class="badge bg-warning text-dark">{{ count($warnings) }}</span>
            </div>
            <div class="card-body p-0">
                @if(count($warnings) > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($warnings as $w)
                            <li class="list-group-item small d-flex">
                                <i class="fas {{ $severityIcon[$w['severity']] ?? 'fa-triangle-exclamation' }} text-{{ $severityBadge[$w['severity']] ?? 'warning' }} me-2 mt-1"></i>
                                <span>
                                    @if(!empty($w['slot']) && isset($slots[$w['slot']]))
                                        <span class="badge bg-light text-dark border me-1">{{ __($slots[$w['slot']]['label']) }}</span>
                                    @endif
                                    {{ $w['message'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-4 text-muted small">
                        <i class="fas fa-check-circle text-success fa-lg mb-2 d-block"></i>
                        {{ __('No weak spots detected. Every step is cited and the chain is complete.') }}
                    </div>
                @endif
            </div>
        </div>

        @if(count($unplacedSlots) > 0)
            <div class="card">
                <div class="card-header"><span class="fw-bold small"><i class="fas fa-puzzle-piece me-1"></i>{{ __('Missing steps') }}</span></div>
                <div class="card-body p-2">
                    @foreach($unplacedSlots as $key => $meta)
                        <form method="POST" action="{{ route('research.argument.steps.add', $project->id) }}" class="d-inline-block mb-1 me-1">
                            @csrf
                            <input type="hidden" name="slot" value="{{ $key }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __($meta['hint']) }}">
                                <i class="fas fa-plus me-1"></i>{{ __($meta['label']) }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
