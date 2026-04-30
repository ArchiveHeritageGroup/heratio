@extends('theme::layouts.2col')
@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection
@section('title', 'Source Assessment — ' . ($io->title ?? ''))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
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
            <div class="card-header"><h5 class="mb-0">{{ __('Assess Source') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('io.research.assessment', $io->slug) }}">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Source Type') }}</label>
                            <select name="source_type" class="form-select">
                                @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->source_type ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Source Form') }}</label>
                            <select name="source_form" class="form-select">
                                @foreach(['original' => 'Original', 'scan' => 'Scan', 'transcription' => 'Transcription', 'translation' => 'Translation', 'born_digital' => 'Born Digital'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->source_form ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Completeness') }}</label>
                            <select name="completeness" class="form-select">
                                @foreach(['complete' => 'Complete', 'partial' => 'Partial', 'fragment' => 'Fragment', 'missing_pages' => 'Missing Pages', 'redacted' => 'Redacted'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($assessment->completeness ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Rationale') }}</label>
                        <textarea name="rationale" class="form-control" rows="3">{{ e($assessment->rationale ?? '') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Bias Context') }}</label>
                        <textarea name="bias_context" class="form-control" rows="2">{{ e($assessment->bias_context ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save Assessment') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Quality Metrics') }}</h5>
                @auth
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMetricModal"><i class="fas fa-plus me-1"></i>{{ __('Add Metric') }}</button>
                @endauth
            </div>
            <div class="card-body p-0">
                @if(!empty($metrics))
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>{{ __('Metric') }}</th><th>{{ __('Value') }}</th><th>{{ __('Source') }}</th><th>{{ __('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($metrics as $m)
                        <tr>
                            <td>{{ e($m->metric_type) }}</td>
                            <td>{{ number_format((float) $m->metric_value, 4) }}</td>
                            <td>{{ e($m->source_service ?? '') }}</td>
                            <td><small>{{ $m->created_at }}</small></td>
                            <td>
                                @auth
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this metric?')">
                                    @csrf
                                    <input type="hidden" name="form_action" value="delete_metric">
                                    <input type="hidden" name="metric_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-times"></i></button>
                                </form>
                                @endauth
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <div class="card-body text-center text-muted py-3">No quality metrics recorded yet.</div>
                @endif
            </div>
        </div>

        {{-- Add Metric Modal --}}
        @auth
        <div class="modal fade" id="addMetricModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST">@csrf<input type="hidden" name="form_action" value="add_metric">
            <div class="modal-header"><h5 class="modal-title">{{ __('Add Quality Metric') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Metric Type *') }}</label>
                    <select name="metric_type" class="form-select" required>
                        <option value="accuracy">{{ __('Accuracy') }}</option>
                        <option value="completeness">{{ __('Completeness') }}</option>
                        <option value="consistency">{{ __('Consistency') }}</option>
                        <option value="timeliness">{{ __('Timeliness') }}</option>
                        <option value="relevance">{{ __('Relevance') }}</option>
                        <option value="readability">{{ __('Readability') }}</option>
                        <option value="ocr_confidence">{{ __('OCR Confidence') }}</option>
                        <option value="ner_confidence">{{ __('NER Confidence') }}</option>
                        <option value="other">{{ __('Other') }}</option>
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Value * (0-1)') }}</label>
                        <input type="number" name="metric_value" class="form-control" step="0.0001" min="0" max="1" required placeholder="{{ __('e.g. 0.8500') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Source Service') }}</label>
                        <input type="text" name="source_service" class="form-control" placeholder="{{ __('e.g. Manual, AI, OCR engine') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button></div>
            </form>
        </div></div></div>
        @endauth
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('Trust Score') }}</h5></div>
            <div class="card-body text-center">
                @php $score = (int) ($assessment->trust_score ?? 0); @endphp
                <div class="display-4 fw-bold text-{{ $score >= 70 ? 'success' : ($score >= 40 ? 'warning' : 'danger') }}">{{ $score }}</div>
                <p class="text-muted">out of 100</p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About') }}</h6></div>
            <div class="card-body small text-muted">
                <p>Source assessment evaluates the reliability and authenticity of archival sources for research purposes.</p>
                <ul class="mb-0">
                    <li><strong>{{ __('Primary') }}</strong> — created at the time of the event</li>
                    <li><strong>{{ __('Secondary') }}</strong> — created after the event using primary sources</li>
                    <li><strong>{{ __('Tertiary') }}</strong> — compilations of primary and secondary sources</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
