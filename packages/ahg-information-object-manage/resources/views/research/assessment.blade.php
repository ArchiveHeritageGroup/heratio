@extends('theme::layouts.2col')
@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection
@section('title', 'Source Assessment — ' . ($io->title ?? ''))

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Source Assessment</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1 class="h2 mb-4">Source Assessment: {{ e($io->title ?? 'Untitled') }}</h1>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Assess Source</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('io.research.assessment', $io->slug) }}">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Source Type</label>
                            <select name="source_type" class="form-select">
                                @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->source_type ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Source Form</label>
                            <select name="source_form" class="form-select">
                                @foreach(['original' => 'Original', 'scan' => 'Scan', 'transcription' => 'Transcription', 'translation' => 'Translation', 'born_digital' => 'Born Digital'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->source_form ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Completeness</label>
                            <select name="completeness" class="form-select">
                                @foreach(['complete' => 'Complete', 'partial' => 'Partial', 'fragment' => 'Fragment', 'missing_pages' => 'Missing Pages', 'redacted' => 'Redacted'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->completeness ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rationale</label>
                        <textarea name="rationale" class="form-control" rows="3">{{ e($assessment->rationale ?? '') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bias Context</label>
                        <textarea name="bias_context" class="form-control" rows="2">{{ e($assessment->bias_context ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Assessment</button>
                </form>
            </div>
        </div>

        @if(!empty($metrics))
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Quality Metrics</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Metric</th><th>Value</th><th>Source</th><th>Date</th></tr></thead>
                    <tbody>
                    @foreach($metrics as $m)
                        <tr>
                            <td>{{ e($m->metric_type) }}</td>
                            <td>{{ number_format((float) $m->metric_value, 4) }}</td>
                            <td>{{ e($m->source_service ?? '') }}</td>
                            <td><small>{{ $m->created_at }}</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Trust Score</h5></div>
            <div class="card-body text-center">
                @php $score = (int) ($assessment->trust_score ?? 0); @endphp
                <div class="display-4 fw-bold text-{{ $score >= 70 ? 'success' : ($score >= 40 ? 'warning' : 'danger') }}">{{ $score }}</div>
                <p class="text-muted">out of 100</p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About</h6></div>
            <div class="card-body small text-muted">
                <p>Source assessment evaluates the reliability and authenticity of archival sources for research purposes.</p>
                <ul class="mb-0">
                    <li><strong>Primary</strong> — created at the time of the event</li>
                    <li><strong>Secondary</strong> — created after the event using primary sources</li>
                    <li><strong>Tertiary</strong> — compilations of primary and secondary sources</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
