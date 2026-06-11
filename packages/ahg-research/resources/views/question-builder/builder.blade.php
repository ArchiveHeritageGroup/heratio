{{--
  Question Builder - Heratio ahg-research (heratio#1226, ROS Stage 2)
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Per-project Research Design Brief. The form refines the research question;
  every save appends a new immutable version with a reason for the change.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-bullseye me-2"></i>{{ __('Question Builder') }} - {{ e($project->title) }}</h1>
    <p class="text-muted mb-0">{{ __('Refine your research question into a structured, versioned design brief before deep source collection.') }}</p>
@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">{{ __('Question Builder') }}</li>
    </ol>
</nav>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if(! $ready)
<div class="alert alert-warning">
    <i class="fas fa-database me-2"></i>{{ __('The Question Builder storage is still being prepared. Reload this page in a moment.') }}
</div>
@else

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        @if($current)
            <span class="badge bg-secondary">{{ __('Current version') }} v{{ $current->version_no }}</span>
            <span class="text-muted small ms-2">{{ __('Last saved') }}: {{ $current->created_at ?? '-' }}</span>
        @else
            <span class="badge bg-light text-dark">{{ __('No version saved yet') }}</span>
        @endif
    </div>
    <a href="{{ route('research.question.history', $project->id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-history me-1"></i>{{ __('Version history') }} ({{ count($versions) }})
    </a>
</div>

<div class="row">
    {{-- ============================ FORM ============================= --}}
    <div class="col-lg-7">
        <form method="post" action="{{ route('research.question.save', $project->id) }}" id="qbForm">
            @csrf

            @php
                $labels = [
                    'broad_topic'         => __('Broad topic'),
                    'problem_statement'   => __('Problem statement'),
                    'research_gap'        => __('Research gap'),
                    'primary_question'    => __('Primary research question'),
                    'secondary_questions' => __('Secondary questions (one per line)'),
                    'hypothesis'          => __('Hypothesis / expected answer'),
                    'scope_boundaries'    => __('Scope and boundaries'),
                    'key_definitions'     => __('Key definitions'),
                    'assumptions'         => __('Assumptions'),
                    'bias_risks'          => __('Bias and risks'),
                ];
                $hints = [
                    'broad_topic'         => __('The general area of interest.'),
                    'problem_statement'   => __('The specific problem or tension you are addressing.'),
                    'research_gap'        => __('What is currently unknown, and why it matters.'),
                    'primary_question'    => __('The single, answerable question at the centre of the study.'),
                    'secondary_questions' => __('Supporting questions that help answer the primary one.'),
                    'hypothesis'          => __('Optional: your provisional answer, for testable designs.'),
                    'scope_boundaries'    => __('Temporal, geographic, population, or evidence limits.'),
                    'key_definitions'     => __('Define the central terms so the question is operationalised.'),
                    'assumptions'         => __('Assumptions about evidence availability and access.'),
                    'bias_risks'          => __('Known biases, sensitivities, or risks to the design.'),
                ];
            @endphp

            <div class="card mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h5 class="mb-0"><i class="fas fa-pen-nib me-2"></i>{{ __('Research Design Brief') }}</h5>
                </div>
                <div class="card-body">
                    @foreach($fields as $f)
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="qb_{{ $f }}">{{ $labels[$f] ?? $f }}</label>
                            <textarea class="form-control qb-field" id="qb_{{ $f }}" name="{{ $f }}"
                                rows="{{ in_array($f, ['primary_question','broad_topic']) ? 2 : 3 }}"
                                >{{ old($f, $values[$f] ?? '') }}</textarea>
                            <div class="form-text">{{ $hints[$f] ?? '' }}</div>
                        </div>
                    @endforeach

                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="qb_change_reason">{{ __('Reason for this change') }}</label>
                        <input type="text" class="form-control" id="qb_change_reason" name="change_reason"
                            maxlength="500" placeholder="{{ __('e.g. Narrowed scope to 1960-1980 after supervisor feedback') }}">
                        <div class="form-text">{{ __('Each save creates a new version that keeps this reason for the audit trail.') }}</div>
                    </div>

                    <input type="hidden" name="status" value="{{ $brief->status ?? 'draft' }}">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>{{ __('Save new version') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="qbRunDiagnosis">
                            <i class="fas fa-stethoscope me-1"></i>{{ __('Run diagnosis') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ========================== DIAGNOSIS ========================== --}}
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>{{ __('Diagnosis') }}</h5>
                @if($aiAvailable)
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="qbUseAi">
                        <label class="form-check-label small" for="qbUseAi">{{ __('Use AI assist') }}</label>
                    </div>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted small">{{ __('Heuristic checks for common design weaknesses. Edit the form, then run diagnosis to re-check.') }}</p>

                <div id="qbFlags">
                    @forelse($diagnosis as $flag)
                        <div class="alert alert-{{ $flag['level'] === 'danger' ? 'danger' : ($flag['level'] === 'warning' ? 'warning' : ($flag['level'] === 'success' ? 'success' : 'info')) }} py-2 mb-2">
                            <strong>{{ $flag['label'] }}</strong>
                            <div class="small">{{ $flag['message'] }}</div>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('Save a version, or run diagnosis, to see checks.') }}</div>
                    @endforelse
                </div>

                <div id="qbAiNote" class="mt-3 d-none">
                    <div class="border rounded p-2 bg-light">
                        <div class="small fw-bold text-uppercase text-muted mb-1">
                            <i class="fas fa-robot me-1"></i>{{ __('AI-assisted note') }}
                        </div>
                        <div id="qbAiNoteBody" class="small" style="white-space:pre-wrap"></div>
                        <div class="small text-muted mt-2"><em>{{ __('Generated by AI via the AHG gateway. Review before relying on it.') }}</em></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    var btn = document.getElementById('qbRunDiagnosis');
    if (!btn) return;
    var url = "{{ route('research.question.diagnose', $project->id) }}";
    var token = "{{ csrf_token() }}";

    function levelClass(level) {
        if (level === 'danger') return 'danger';
        if (level === 'warning') return 'warning';
        if (level === 'success') return 'success';
        return 'info';
    }

    btn.addEventListener('click', function () {
        var fields = {{ Illuminate\Support\Js::from($fields) }};
        var body = new URLSearchParams();
        body.append('_token', token);
        fields.forEach(function (f) {
            var el = document.getElementById('qb_' + f);
            body.append(f, el ? el.value : '');
        });
        var aiToggle = document.getElementById('qbUseAi');
        body.append('use_ai', (aiToggle && aiToggle.checked) ? '1' : '0');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __('Diagnosing...') }}';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            var wrap = document.getElementById('qbFlags');
            wrap.innerHTML = '';
            if (data && data.flags && data.flags.length) {
                data.flags.forEach(function (flag) {
                    var div = document.createElement('div');
                    div.className = 'alert alert-' + levelClass(flag.level) + ' py-2 mb-2';
                    var strong = document.createElement('strong');
                    strong.textContent = flag.label;
                    var msg = document.createElement('div');
                    msg.className = 'small';
                    msg.textContent = flag.message;
                    div.appendChild(strong);
                    div.appendChild(msg);
                    wrap.appendChild(div);
                });
            } else {
                wrap.innerHTML = '<div class="text-muted small">{{ __('No checks returned.') }}</div>';
            }

            var aiWrap = document.getElementById('qbAiNote');
            var aiBody = document.getElementById('qbAiNoteBody');
            if (data && data.ai_note) {
                aiBody.textContent = data.ai_note;
                aiWrap.classList.remove('d-none');
            } else {
                aiWrap.classList.add('d-none');
            }
        }).catch(function () {
            var wrap = document.getElementById('qbFlags');
            wrap.innerHTML = '<div class="alert alert-warning py-2 mb-2 small">{{ __('Could not run diagnosis. Please try again.') }}</div>';
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-stethoscope me-1"></i>{{ __('Run diagnosis') }}';
        });
    });
})();
</script>
@endpush

@endif
@endsection
