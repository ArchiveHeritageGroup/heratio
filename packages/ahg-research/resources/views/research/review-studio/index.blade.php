{{-- Review Studio - Research OS Stage 14 (heratio#1230) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Review Studio')

@php
    use Illuminate\Support\Str;
    $personas      = $personas ?? [];
    $findingGroups = $findingGroups ?? [];
    $comments      = is_array($comments) ? $comments : [];
    $claims        = is_array($claims) ? $claims : [];
    $runs          = is_array($runs) ? $runs : [];
    $aiLabel       = $aiLabel ?? 'AI reviewer - via the AHG gateway, not a human reviewer';
    $assertionFilter = $assertionFilter ?? null;
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Review Studio') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('ai_warning'))
    <div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-robot me-1"></i>{{ session('ai_warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-user-edit text-primary me-2"></i>{{ __('Review Studio') }}</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<p class="text-muted">{{ __('Supervisor and co-author comment threads anchored to your claims, plus an adversarial reviewer simulation. Comments work with or without AI; the simulation is clearly labelled as machine-generated.') }}</p>

<div class="row g-4">

    {{-- ============================================================== --}}
    {{-- HALF 1: COMMENT THREADS                                        --}}
    {{-- ============================================================== --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-comments text-primary me-1"></i>{{ __('Comments') }}</span>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newCommentForm">
                    <i class="fas fa-plus me-1"></i>{{ __('New comment') }}
                </button>
            </div>

            {{-- Filter bar --}}
            <div class="card-body border-bottom py-2">
                <form method="GET" action="{{ route('research.review.index', $project->id) }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label-sm text-muted mb-0">{{ __('Anchor') }}</label>
                    </div>
                    <div class="col">
                        <select name="claim" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">{{ __('All comments') }}</option>
                            <option value="" disabled>--</option>
                            @foreach($claims as $cl)
                                <option value="{{ $cl->id }}" @selected($assertionFilter === (int) $cl->id)>
                                    {{ e(Str::limit($cl->label ?? ('Claim #'.$cl->id), 60)) }}@if(($cl->comment_count ?? 0) > 0) ({{ $cl->comment_count }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="showResolved" name="resolved" value="1"
                               @checked($includeResolved ?? true) onchange="this.value=this.checked?'1':'0'; this.form.submit()">
                        <label class="form-check-label small" for="showResolved">{{ __('Show resolved') }}</label>
                    </div>
                </form>
            </div>

            {{-- New comment form (collapsed) --}}
            <div class="collapse @if(old('body')) show @endif" id="newCommentForm">
                <div class="card-body border-bottom bg-light">
                    <form method="POST" action="{{ route('research.review.comments.store', $project->id) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-bold">{{ __('Anchor to a claim') }}</label>
                            <select name="assertion_id" class="form-select form-select-sm">
                                <option value="">{{ __('Whole project (no specific claim)') }}</option>
                                @foreach($claims as $cl)
                                    <option value="{{ $cl->id }}" @selected($assertionFilter === (int) $cl->id)>
                                        {{ e(Str::limit($cl->label ?? ('Claim #'.$cl->id), 80)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="body" class="form-control form-control-sm" rows="3" maxlength="10000"
                                      placeholder="{{ __('Add a comment for the supervisor or co-authors...') }}" required>{{ old('body') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>{{ __('Post comment') }}</button>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                @forelse($comments as $c)
                    <div class="border-bottom p-3 @if($c->resolved) bg-light @endif">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2">
                                <span class="fw-bold">{{ e($c->author_name ?? 'User') }}</span>
                                <span class="text-muted small ms-1">{{ \Illuminate\Support\Carbon::parse($c->created_at)->diffForHumans() }}</span>
                                @if($c->resolved)
                                    <span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>{{ __('Resolved') }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Anchored-to-claim chip --}}
                        @if(!empty($c->assertion_id))
                            @php $anchor = collect($claims)->firstWhere('id', (int) $c->assertion_id); @endphp
                            <a href="{{ route('research.review.index', $project->id) }}?claim={{ $c->assertion_id }}"
                               class="badge rounded-pill bg-info-subtle text-info-emphasis text-decoration-none mb-1 d-inline-block">
                                <i class="fas fa-thumbtack me-1"></i>{{ e(Str::limit($anchor->label ?? ('Claim #'.$c->assertion_id), 50)) }}
                            </a>
                        @else
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis mb-1 d-inline-block">
                                <i class="fas fa-folder-open me-1"></i>{{ __('Project-level') }}
                            </span>
                        @endif

                        <div class="mt-1" style="white-space:pre-line">{{ e($c->body) }}</div>

                        {{-- Replies --}}
                        @if(!empty($c->replies))
                            <div class="ms-4 mt-2 ps-2 border-start">
                                @foreach($c->replies as $rep)
                                    <div class="mb-2">
                                        <span class="fw-bold small">{{ e($rep->author_name ?? 'User') }}</span>
                                        <span class="text-muted small ms-1">{{ \Illuminate\Support\Carbon::parse($rep->created_at)->diffForHumans() }}</span>
                                        <div class="small" style="white-space:pre-line">{{ e($rep->body) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="mt-2 d-flex gap-2 align-items-center">
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#reply{{ $c->id }}">
                                <i class="fas fa-reply me-1"></i>{{ __('Reply') }}
                            </button>
                            <form method="POST" action="{{ route('research.review.comments.resolve', [$project->id, $c->id]) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="resolved" value="{{ $c->resolved ? 0 : 1 }}">
                                <button class="btn btn-link btn-sm p-0 text-decoration-none" type="submit">
                                    <i class="fas {{ $c->resolved ? 'fa-undo' : 'fa-check' }} me-1"></i>{{ $c->resolved ? __('Reopen') : __('Resolve') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('research.review.comments.destroy', [$project->id, $c->id]) }}" class="d-inline"
                                  onsubmit="return confirm('{{ __('Delete this comment and any replies?') }}');">
                                @csrf
                                <button class="btn btn-link btn-sm p-0 text-decoration-none text-danger" type="submit">
                                    <i class="fas fa-trash me-1"></i>{{ __('Delete') }}
                                </button>
                            </form>
                        </div>

                        {{-- Reply form (collapsed) --}}
                        <div class="collapse mt-2" id="reply{{ $c->id }}">
                            <form method="POST" action="{{ route('research.review.comments.store', $project->id) }}">
                                @csrf
                                <input type="hidden" name="thread_id" value="{{ $c->id }}">
                                <div class="input-group input-group-sm">
                                    <textarea name="body" class="form-control" rows="2" maxlength="10000"
                                              placeholder="{{ __('Write a reply...') }}" required></textarea>
                                    <button class="btn btn-outline-primary" type="submit"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comments fa-2x mb-2 d-block opacity-50"></i>
                        {{ __('No comments yet. Start a supervisor or co-author thread - anchor it to a claim or to the whole project.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- HALF 2: ADVERSARIAL REVIEWER TWIN                              --}}
    {{-- ============================================================== --}}
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-robot text-secondary me-1"></i>{{ __('Adversarial reviewer') }}</span>
            </div>
            <div class="card-body">
                {{-- Mandatory AI provenance label --}}
                <div class="alert alert-secondary py-2 small mb-3">
                    <i class="fas fa-robot me-1"></i><strong>{{ $aiLabel }}.</strong>
                    {{ __('Treat the output as a critique prompt, not a verdict.') }}
                </div>

                <p class="small text-muted">{{ __('Simulate a tough peer reviewer against this project\'s brief and claims. Pick a persona, then run the simulation.') }}</p>

                <form method="POST" action="{{ route('research.review.run', $project->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold">{{ __('Reviewer persona') }}</label>
                        <select name="persona" class="form-select form-select-sm">
                            @foreach($personas as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm w-100">
                        <i class="fas fa-play me-1"></i>{{ __('Run reviewer simulation') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Run history --}}
        <div class="card">
            <div class="card-header fw-bold"><i class="fas fa-history text-muted me-1"></i>{{ __('Simulation history') }}</div>
            <div class="card-body p-0">
                @forelse($runs as $run)
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-secondary">{{ $run->persona_label ?? $run->persona }}</span>
                                <span class="text-muted small ms-1">{{ \Illuminate\Support\Carbon::parse($run->created_at)->diffForHumans() }}</span>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="{{ route('research.review.runs.show', [$project->id, $run->id]) }}" class="btn btn-outline-primary btn-sm py-0">{{ __('Open') }}</a>
                                <form method="POST" action="{{ route('research.review.runs.destroy', [$project->id, $run->id]) }}"
                                      onsubmit="return confirm('{{ __('Delete this simulation run?') }}');">
                                    @csrf
                                    <button class="btn btn-outline-danger btn-sm py-0" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        @if(!empty($run->summary))
                            <div class="small text-muted mt-1">{{ e(Str::limit($run->summary, 160)) }}</div>
                        @endif
                        @if(!empty($run->model))
                            <div class="small text-muted mt-1"><i class="fas fa-microchip me-1"></i>{{ e($run->model) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-4 text-muted small">
                        <i class="fas fa-history fa-2x mb-2 d-block opacity-50"></i>
                        {{ __('No simulations run yet. Pick a persona above and run one.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
