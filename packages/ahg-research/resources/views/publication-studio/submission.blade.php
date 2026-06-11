{{--
  Publication Studio - submission detail - Heratio ahg-research (heratio#1232)
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Compliance checklist + response-to-reviewers + status timeline + DOI/deposit
  fields for a single submission. Empty-states throughout; never 500s.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-paper-plane me-2"></i>{{ e($submission['venue_name']) }}</h1>
    <p class="text-muted mb-0">{{ __('Publication Studio submission for') }} {{ e($project->title) }}</p>
@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.publication.index', $project->id) }}">{{ __('Publication Studio') }}</a></li>
        <li class="breadcrumb-item active">{{ e($submission['venue_name']) }}</li>
    </ol>
</nav>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row">
    {{-- ============ LEFT: status + checklist + responses ============ --}}
    <div class="col-lg-7">

        {{-- Status timeline + transitions --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-route me-2"></i>{{ __('Status') }}</h5>
                @include('research::publication-studio._status_badge', ['status' => $submission['status']])
            </div>
            <div class="card-body">
                <ol class="d-flex flex-wrap gap-2 list-unstyled mb-3 small">
                    @foreach($statuses as $st)
                        @php $isCurrent = ($submission['status'] ?? '') === $st; @endphp
                        <li>
                            <span class="badge {{ $isCurrent ? 'bg-dark' : 'bg-light text-dark border' }}">{{ __(ucfirst($st)) }}</span>
                            @if(! $loop->last)<i class="fas fa-chevron-right text-muted small"></i>@endif
                        </li>
                    @endforeach
                </ol>

                @if(count($nextStatuses))
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($nextStatuses as $to)
                            <form method="post" action="{{ route('research.publication.submission.transition', [$project->id, $submission['id']]) }}">
                                @csrf
                                <input type="hidden" name="to" value="{{ $to }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right me-1"></i>{{ __('Move to') }} {{ __(ucfirst($to)) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">{{ __('This submission is in a terminal state.') }}</p>
                @endif

                <div class="row mt-3 small text-muted">
                    <div class="col">{{ __('Submitted') }}: {{ $submission['submitted_at'] ?? '-' }}</div>
                    <div class="col">{{ __('Decision') }}: {{ $submission['decision_at'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        {{-- Compliance checklist --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>{{ __('Compliance checklist') }}</h5>
                <span class="badge bg-light text-dark">{{ $reqMet }}/{{ $reqTotal }}</span>
            </div>
            <div class="card-body">
                @if($reqTotal > 0)
                    @php $pct = $reqTotal > 0 ? round($reqMet / $reqTotal * 100) : 0; @endphp
                    <div class="progress mb-3" style="height:6px">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%"></div>
                    </div>
                @endif

                @forelse($requirements as $r)
                    <form method="post" action="{{ route('research.publication.requirement.update', [$project->id, $submission['id'], $r['id']]) }}" class="border-bottom py-2">
                        @csrf
                        <div class="d-flex align-items-start">
                            <div class="form-check me-2 mt-1">
                                <input type="checkbox" class="form-check-input" name="met" value="1" @checked(!empty($r['met'])) onchange="this.form.submit()">
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold {{ !empty($r['met']) ? 'text-success' : '' }}">{{ e($r['label']) }}</div>
                                <input type="text" name="note" value="{{ e($r['note'] ?? '') }}" class="form-control form-control-sm mt-1" placeholder="{{ __('Note (optional)') }}">
                            </div>
                            <div class="ms-2 d-flex flex-column gap-1">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Save note') }}"><i class="fas fa-save"></i></button>
                            </div>
                        </div>
                    </form>
                    <form method="post" action="{{ route('research.publication.requirement.delete', [$project->id, $submission['id'], $r['id']]) }}" class="text-end" onsubmit="return confirm('{{ __('Remove this requirement?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0 mb-2"><i class="fas fa-trash me-1"></i>{{ __('Remove') }}</button>
                    </form>
                @empty
                    <p class="text-muted mb-3"><i class="fas fa-circle-info me-1"></i>{{ __('No requirements on the checklist yet. Add the venue-specific requirements below.') }}</p>
                @endforelse

                <form method="post" action="{{ route('research.publication.requirement.add', [$project->id, $submission['id']]) }}" class="row g-2 mt-2">
                    @csrf
                    <div class="col-9"><input type="text" name="label" class="form-control form-control-sm" placeholder="{{ __('Add a requirement (e.g. cover letter, suggested reviewers)') }}" required></div>
                    <div class="col-3"><button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button></div>
                </form>
            </div>
        </div>

        {{-- Response to reviewers / revision history --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-comments me-2"></i>{{ __('Response to reviewers and revisions') }}</h5></div>
            <div class="card-body">
                @forelse($responses as $resp)
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">{{ e($resp['reviewer_label'] ?? __('Reviewer')) }}</span>
                            <span class="text-muted small">{{ $resp['created_at'] ?? '' }}</span>
                        </div>
                        @if(!empty($resp['point']))<div class="small mt-1"><span class="text-muted">{{ __('Point') }}:</span> {{ e($resp['point']) }}</div>@endif
                        @if(!empty($resp['response']))<div class="small mt-1"><span class="text-muted">{{ __('Response') }}:</span> {{ e($resp['response']) }}</div>@endif
                        @if(!empty($resp['revision_note']))<div class="small mt-1"><span class="text-muted">{{ __('Revision') }}:</span> {{ e($resp['revision_note']) }}</div>@endif
                    </div>
                @empty
                    <p class="text-muted"><i class="fas fa-circle-info me-1"></i>{{ __('No reviewer responses recorded yet. Add a point, your response, and what you changed below.') }}</p>
                @endforelse

                <form method="post" action="{{ route('research.publication.response.add', [$project->id, $submission['id']]) }}" class="mt-2">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4"><input type="text" name="reviewer_label" class="form-control form-control-sm" placeholder="{{ __('Reviewer label (e.g. Reviewer 1)') }}"></div>
                    </div>
                    <textarea name="point" class="form-control form-control-sm mt-2" rows="2" placeholder="{{ __('Reviewer point / requested change') }}"></textarea>
                    <textarea name="response" class="form-control form-control-sm mt-2" rows="2" placeholder="{{ __('Your response') }}"></textarea>
                    <textarea name="revision_note" class="form-control form-control-sm mt-2" rows="2" placeholder="{{ __('What you changed in the manuscript') }}"></textarea>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-plus me-1"></i>{{ __('Record response') }}</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ============ RIGHT: venue + deposit + AI fit ============ --}}
    <div class="col-lg-5">

        {{-- Venue --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-book-journal-whills me-2"></i>{{ __('Venue') }}</h5></div>
            <div class="card-body">
                <div class="fw-semibold">{{ e($submission['venue_name']) }}</div>
                @if($journal)
                    @if(!empty($journal['publisher']))<div class="text-muted small">{{ e($journal['publisher']) }}</div>@endif
                    @if(!empty($journal['subject_scope']))<div class="small mt-1">{{ \Illuminate\Support\Str::limit($journal['subject_scope'], 220) }}</div>@endif
                    <div class="mt-2">
                        @if(!empty($journal['accreditation']))<span class="badge bg-secondary">{{ e($journal['accreditation']) }}</span>@endif
                        @if(!empty($journal['reference_style']))<span class="badge bg-light text-dark">{{ e($journal['reference_style']) }}</span>@endif
                        @if(!empty($journal['open_access']))<span class="badge bg-success">{{ __('Open access') }}</span>@endif
                    </div>
                    @if(!empty($journal['submission_url']))
                        <a href="{{ e($journal['submission_url']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-up-right-from-square me-1"></i>{{ __('Submission portal') }}</a>
                    @endif

                    @if($aiAvailable)
                        <hr>
                        <button type="button" class="btn btn-sm btn-outline-info" id="aiFitBtn" data-venue="{{ $journal['id'] }}">
                            <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('AI fit suggestion') }}
                        </button>
                        <div id="aiFitOut" class="small mt-2"></div>
                        <div class="text-muted small mt-1"><i class="fas fa-robot me-1"></i>{{ __('AI-generated guidance via the AHG gateway. Verify before relying on it.') }}</div>
                    @endif
                @else
                    <div class="text-muted small mt-1">{{ __('Free-text venue (not linked to a directory journal).') }}</div>
                @endif
            </div>
        </div>

        {{-- Deposit / metadata --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>{{ __('Deposit and metadata') }}</h5></div>
            <div class="card-body">
                <form method="post" action="{{ route('research.publication.submission.update', [$project->id, $submission['id']]) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small mb-1">{{ __('Manuscript title') }}</label>
                        <input type="text" name="manuscript_title" value="{{ e($submission['manuscript_title'] ?? '') }}" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">{{ __('Venue name') }}</label>
                        <input type="text" name="venue_name" value="{{ e($submission['venue_name']) }}" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">{{ __('DOI') }}</label>
                        <input type="text" name="doi" value="{{ e($submission['doi'] ?? '') }}" class="form-control form-control-sm" placeholder="10.xxxx/xxxxx">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">{{ __('Repository / deposit URL') }}</label>
                        <input type="url" name="repository_url" value="{{ e($submission['repository_url'] ?? '') }}" class="form-control form-control-sm" placeholder="https://">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="3" class="form-control form-control-sm">{{ e($submission['notes'] ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@if($aiAvailable && $journal)
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('aiFitBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var out = document.getElementById('aiFitOut');
        out.innerHTML = '<span class="text-muted">{{ __('Thinking...') }}</span>';
        btn.disabled = true;
        var data = new FormData();
        data.append('_token', '{{ csrf_token() }}');
        data.append('venue_ref', btn.dataset.venue);
        fetch('{{ route('research.publication.ai-fit', $project->id) }}', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.disabled = false;
                if (j && j.note) {
                    var esc = j.note.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    out.innerHTML = '<div class="border rounded p-2 bg-light"><pre class="mb-0" style="white-space:pre-wrap;font-family:inherit">' + esc + '</pre></div>';
                } else {
                    out.innerHTML = '<span class="text-muted">{{ __('No suggestion available right now.') }}</span>';
                }
            })
            .catch(function () { btn.disabled = false; out.innerHTML = '<span class="text-danger">{{ __('Could not reach the AI service.') }}</span>'; });
    });
});
</script>
@endif

@endsection
