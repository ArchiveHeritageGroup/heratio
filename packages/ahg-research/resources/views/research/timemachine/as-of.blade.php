{{-- Time Machine "state as of" snapshot - Research OS moonshot 19 (heratio#1240). The honesty engine. --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Time Machine - State as of'))

@php
    $project   = $project ?? null;
    $projectId = $projectId ?? ($project->id ?? 0);
    $state     = is_array($state ?? null) ? $state : [];
    $asOf      = $state['asOf'] ?? ($asOf ?? null);
    $brief     = $state['brief'] ?? null;
    $claims    = is_array($state['claims'] ?? null) ? $state['claims'] : [];
    $decisions = is_array($state['decisions'] ?? null) ? $state['decisions'] : [];
    $arguments = is_array($state['arguments'] ?? null) ? $state['arguments'] : [];
    $inbox     = is_array($state['inbox'] ?? null) ? $state['inbox'] : [];
    $methods   = is_array($state['methods'] ?? null) ? $state['methods'] : [];
    $minDate   = $minDate ?? null;
    $maxDate   = $maxDate ?? null;
    $dateValue = $asOf ? $asOf->format('Y-m-d') : ($requested ?? '');

    $totalItems = count($claims) + count($decisions) + count($arguments) + count($inbox) + count($methods) + ($brief ? 1 : 0);
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $projectId) }}">{{ e($project->title ?? __('Project')) }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.timemachine.index', $projectId) }}">{{ __('Time Machine') }}</a></li>
        <li class="breadcrumb-item active">{{ __('State as of') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="fas fa-calendar-day text-primary me-2"></i>{{ __('State as of a date') }}</h1>
    <a href="{{ route('research.timemachine.index', $projectId) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to timeline') }}
    </a>
</div>

<p class="text-muted">{{ __('Move the scrubber to reconstruct exactly what this project looked like on, or before, any chosen date - which version of the question was current, which claims had been made, which decisions had been taken. Pure reconstruction from the existing record; nothing is rewound or changed.') }}</p>

{{-- Date scrubber --}}
<form method="GET" action="{{ route('research.timemachine.asOf', $projectId) }}" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-4">
                <label for="date" class="form-label small fw-semibold mb-1">{{ __('Show the project as it stood on or before') }}</label>
                <input type="date" id="date" name="date" class="form-control"
                       value="{{ $dateValue }}"
                       @if($minDate) min="{{ $minDate->format('Y-m-d') }}" @endif
                       @if($maxDate) max="{{ $maxDate->format('Y-m-d') }}" @endif>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-rotate-left me-1"></i>{{ __('Rewind') }}</button>
            </div>
            @if($maxDate)
                <div class="col-auto">
                    <a href="{{ route('research.timemachine.asOf', $projectId) }}?date={{ $maxDate->format('Y-m-d') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-forward-fast me-1"></i>{{ __('Latest') }}
                    </a>
                </div>
            @endif
        </div>
        @if($asOf)
            <p class="text-muted small mt-2 mb-0">
                {{ __('Reconstructing everything recorded up to') }}
                <strong>{{ $asOf->format('d M Y, H:i') }}</strong>.
            </p>
        @endif
    </div>
</form>

@if($totalItems === 0)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-hourglass-start fa-2x mb-3 d-block"></i>
            <p class="mb-1">{{ __('Nothing had been recorded for this project by that date.') }}</p>
            <p class="small mb-0">{{ __('Try a later date, or jump to the latest state.') }}</p>
        </div>
    </div>
@else
    <div class="row g-3">
        {{-- Question brief snapshot --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-question me-2"></i>{{ __('Question brief') }}</span>
                    @if($brief)
                        <span class="badge bg-primary">v{{ (int) ($brief->version_no ?? 0) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($brief)
                        @php
                            $fields = [
                                'broad_topic'        => __('Broad topic'),
                                'problem_statement'  => __('Problem statement'),
                                'research_gap'       => __('Research gap'),
                                'primary_question'   => __('Primary question'),
                                'secondary_questions'=> __('Secondary questions'),
                                'hypothesis'         => __('Hypothesis'),
                                'scope_boundaries'   => __('Scope boundaries'),
                                'key_definitions'    => __('Key definitions'),
                                'assumptions'        => __('Assumptions'),
                                'bias_risks'         => __('Bias risks'),
                            ];
                            $anyField = false;
                        @endphp
                        @if(!empty($brief->created_at))
                            <p class="small text-muted mb-3">{{ __('This version was current as of your chosen date (saved') }} {{ \Illuminate\Support\Str::limit((string) $brief->created_at, 16, '') }}).</p>
                        @endif
                        <dl class="row mb-0">
                            @foreach($fields as $col => $label)
                                @php $val = trim((string) ($brief->$col ?? '')); @endphp
                                @if($val !== '')
                                    @php $anyField = true; @endphp
                                    <dt class="col-sm-3 small text-muted">{{ $label }}</dt>
                                    <dd class="col-sm-9">{{ $val }}</dd>
                                @endif
                            @endforeach
                        </dl>
                        @if(!$anyField)
                            <p class="text-muted small mb-0">{{ __('This brief version had no content filled in yet.') }}</p>
                        @endif
                        @if(!empty($brief->change_reason))
                            <p class="text-muted small mt-2 mb-0"><em>{{ __('Change reason') }}:</em> {{ e($brief->change_reason) }}</p>
                        @endif
                    @else
                        <p class="text-muted small mb-0">{{ __('No question brief had been saved by this date.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Claims --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-flask me-2"></i>{{ __('Claims') }}</span>
                    <span class="badge bg-success">{{ count($claims) }}</span>
                </div>
                <div class="card-body">
                    @forelse($claims as $c)
                        @php
                            $label = trim(implode(' ', array_filter([
                                (string) ($c->subject_label ?? ''),
                                (string) ($c->predicate ?? ''),
                                (string) ($c->object_label ?? '') !== '' ? (string) ($c->object_label ?? '') : (string) ($c->object_value ?? ''),
                            ], fn ($p) => trim($p) !== '')));
                            $status = trim((string) ($c->status ?? ''));
                        @endphp
                        <div class="mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="small">{{ $label !== '' ? $label : __('(claim)') }}</div>
                            @if($status !== '')
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ $status }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No claims had been recorded by this date.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Decisions --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-gavel me-2"></i>{{ __('Decisions') }}</span>
                    <span class="badge bg-info">{{ count($decisions) }}</span>
                </div>
                <div class="card-body">
                    @forelse($decisions as $d)
                        <div class="mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="small fw-semibold">{{ e($d->summary ?? __('Decision')) }}</div>
                            @if(trim((string) ($d->reason ?? '')) !== '')
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $d->reason, 160) }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No decisions had been logged by this date.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Arguments --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-diagram-project me-2"></i>{{ __('Arguments') }}</span>
                    <span class="badge bg-warning text-dark">{{ count($arguments) }}</span>
                </div>
                <div class="card-body">
                    @forelse($arguments as $a)
                        <div class="mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="small fw-semibold">{{ e($a->title ?? __('Untitled argument')) }}</div>
                            <div class="small text-muted">{{ trans_choice('{0}no steps yet|{1}1 step|[2,*]:count steps', (int) ($a->step_count ?? 0), ['count' => (int) ($a->step_count ?? 0)]) }}</div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No arguments had been started by this date.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Captured items --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-secondary-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-inbox me-2"></i>{{ __('Captured items') }}</span>
                    <span class="badge bg-secondary">{{ count($inbox) }}</span>
                </div>
                <div class="card-body">
                    @forelse($inbox as $i)
                        <div class="mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="small">
                                <span class="badge bg-light text-dark border me-1">{{ e($i->kind ?? 'note') }}</span>
                                {{ e($i->title ?? __('untitled')) }}
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('Nothing had been captured by this date.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Method protocols --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark-subtle d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-microscope me-2"></i>{{ __('Method protocols') }}</span>
                    <span class="badge bg-dark">{{ count($methods) }}</span>
                </div>
                <div class="card-body">
                    @forelse($methods as $m)
                        <div class="mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="small fw-semibold">{{ e($m->title ?? __('Protocol')) }}</div>
                            <div class="small text-muted">
                                @if(trim((string) ($m->template_code ?? '')) !== '')
                                    <span class="me-2">{{ e($m->template_code) }}</span>
                                @endif
                                @if(trim((string) ($m->status ?? '')) !== '')
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ e($m->status) }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No method protocols had been defined by this date.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
