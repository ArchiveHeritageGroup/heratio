{{-- AI Disclosure Statement + interaction log - Research OS Part IV (heratio#1242) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('AI Disclosure'))

@php
    $lines         = is_array($lines ?? null) ? $lines : [];
    $statement     = (string) ($statement ?? '');
    $projectId     = $project->id ?? 0;
    $detectedCount = (int) ($detectedCount ?? 0);
    $loggedCount   = (int) ($loggedCount ?? 0);

    $sourceBadge = [
        'detected' => 'info',
        'logged'   => 'secondary',
    ];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $projectId) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('AI Disclosure') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="fas fa-shield-halved text-primary me-2"></i>{{ __('AI Disclosure') }}</h1>
    <a href="{{ route('research.aidisclosure.statement', $projectId) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-download me-1"></i>{{ __('Download statement (.txt)') }}
    </a>
</div>

<p class="text-muted">
    {{ __('A one-click AI-use disclosure for this project. Detected lines are aggregated read-only from the project\'s AI-assisted slices; logged lines are recorded by you. All AI calls in Heratio route through the AHG AI gateway.') }}
</p>

{{-- The generated statement, with a copy button. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-lines me-2"></i>{{ __('AI Disclosure Statement') }}</span>
        <button type="button" class="btn btn-primary btn-sm" id="copy-statement-btn"
                data-copy-target="#ai-disclosure-statement">
            <i class="fas fa-copy me-1"></i>{{ __('Copy') }}
        </button>
    </div>
    <div class="card-body">
        <textarea id="ai-disclosure-statement" class="form-control" rows="14" readonly
                  style="font-family: var(--bs-font-monospace); font-size: .85rem;">{{ $statement }}</textarea>
        <div class="form-text mt-2">
            {{ __('Paste this into a journal\'s AI-use statement. It asserts that you remained the author and that AI was assistive only, routed through the AHG gateway. Assembled from records - no AI call is made to generate it.') }}
        </div>
    </div>
</div>

{{-- Detected + logged usage table. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-list-check me-2"></i>{{ __('Recorded AI usage') }}</span>
        <span class="text-muted small">
            {{ $detectedCount }} {{ __('detected') }} &middot; {{ $loggedCount }} {{ __('logged') }}
        </span>
    </div>
    @if(empty($lines))
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-robot fa-2x mb-3 d-block opacity-50"></i>
            <p class="mb-0">{{ __('No AI assistance recorded for this project.') }}</p>
            <p class="small mb-0">{{ __('Detected AI usage will appear here automatically as you use AI-assisted research tools. You can also record AI use manually below.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Where') }}</th>
                        <th>{{ __('Purpose') }}</th>
                        <th>{{ __('Tool') }}</th>
                        <th>{{ __('Model') }}</th>
                        <th>{{ __('When') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $l)
                        @php $src = (string) ($l['source'] ?? 'detected'); @endphp
                        <tr>
                            <td><span class="badge bg-{{ $sourceBadge[$src] ?? 'secondary' }}">{{ $src === 'logged' ? __('Logged') : __('Detected') }}</span></td>
                            <td>{{ e($l['slice'] ?? '') }}</td>
                            <td class="small">{{ e($l['purpose'] ?? '') }}</td>
                            <td class="small">{{ e($l['tool'] ?? '') }}</td>
                            <td class="small">{{ ($l['model'] ?? null) ? e($l['model']) : '-' }}</td>
                            <td class="small text-nowrap">{{ ($l['when'] ?? null) ? e(substr((string) $l['when'], 0, 16)) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Manual log entries (with per-row delete) + add form. --}}
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-pen-to-square me-2"></i>{{ __('Record an AI interaction') }}</div>
    <div class="card-body">
        <p class="text-muted small">
            {{ __('Use this to record AI assistance the system cannot detect on its own (for example a model used outside Heratio). Detected slice usage is added automatically and does not need logging here.') }}
        </p>
        <form method="POST" action="{{ route('research.aidisclosure.log.store', $projectId) }}" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label">{{ __('Tool') }} <span class="text-danger">*</span></label>
                <input type="text" name="tool" class="form-control @error('tool') is-invalid @enderror"
                       maxlength="160" required value="{{ old('tool', 'AHG AI gateway') }}">
                @error('tool')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Model') }}</label>
                <input type="text" name="model" class="form-control @error('model') is-invalid @enderror"
                       maxlength="160" value="{{ old('model') }}" placeholder="{{ __('optional') }}">
                @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Output reference') }}</label>
                <input type="text" name="output_ref" class="form-control @error('output_ref') is-invalid @enderror"
                       maxlength="500" value="{{ old('output_ref') }}" placeholder="{{ __('e.g. a section, figure, or DOI') }}">
                @error('output_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('Purpose') }} <span class="text-danger">*</span></label>
                <textarea name="purpose" class="form-control @error('purpose') is-invalid @enderror"
                          rows="2" maxlength="2000" required
                          placeholder="{{ __('What was the AI used for?') }}">{{ old('purpose') }}</textarea>
                @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add to log') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('copy-statement-btn');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        var target = document.querySelector(btn.getAttribute('data-copy-target'));
        if (!target) { return; }
        target.select();
        target.setSelectionRange(0, target.value.length);
        var done = function () {
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Copied') }}';
            setTimeout(function () { btn.innerHTML = original; }, 1500);
        };
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(target.value).then(done, function () {
                    try { document.execCommand('copy'); done(); } catch (e) {}
                });
            } else {
                document.execCommand('copy');
                done();
            }
        } catch (e) {
            try { document.execCommand('copy'); done(); } catch (e2) {}
        }
    });
})();
</script>
@endpush
