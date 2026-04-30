{{-- Extraction Jobs — cloned from AtoM ahgResearchPlugin/extractionJobsSuccess.php --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Extraction Jobs</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">{{ __('AI Extraction Jobs') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobModal"><i class="fas fa-robot me-1"></i> {{ __('New Job') }}</button>
</div>

{{-- Status filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">{{ __('Status') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>{{ __('Queued') }}</option>
                    <option value="running" {{ request('status') === 'running' ? 'selected' : '' }}>{{ __('Running') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button></div>
        </form>
    </div>
</div>

@if(empty($jobs) || count($jobs) === 0)
    <div class="alert alert-info">No extraction jobs yet. Click "New Job" to create one.</div>
@else
<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
        @foreach($jobs as $j)
            @php
                $pct = ($j->total_items ?? 0) > 0 ? round((($j->processed_items ?? 0) / $j->total_items) * 100) : 0;
            @endphp
            <tr data-job-id="{{ $j->id }}" data-status="{{ $j->status ?? '' }}">
                <td><span class="badge bg-light text-dark">{{ e($j->extraction_type ?? '') }}</span></td>
                <td><span class="badge bg-{{ match($j->status ?? '') { 'queued' => 'secondary', 'running' => 'primary', 'completed' => 'success', 'failed' => 'danger', default => 'dark' } }} job-status">{{ ucfirst($j->status ?? '') }}</span></td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar job-progress" style="width: {{ $pct }}%">{{ (int)($j->processed_items ?? 0) }}/{{ (int)($j->total_items ?? 0) }}</div>
                    </div>
                </td>
                <td><small>{{ $j->created_at ?? '' }}</small></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        @if(in_array($j->status ?? '', ['queued', 'running']))
                            <form method="post" action="{{ route('research.extractionJobs', $project->id) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="form_action" value="cancel">
                                <input type="hidden" name="job_id" value="{{ $j->id }}">
                                <button type="submit" class="btn btn-outline-danger" title="{{ __('Cancel') }}" onclick="return confirm('Cancel this job?')"><i class="fas fa-stop"></i></button>
                            </form>
                        @elseif(($j->status ?? '') === 'failed')
                            <form method="post" action="{{ route('research.extractionJobs', $project->id) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="form_action" value="retry">
                                <input type="hidden" name="job_id" value="{{ $j->id }}">
                                <button type="submit" class="btn btn-outline-warning" title="{{ __('Retry') }}"><i class="fas fa-redo"></i></button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Create Job Modal --}}
<div class="modal fade" id="createJobModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('research.extractionJobs', $project->id) }}">
            @csrf
            <input type="hidden" name="form_action" value="create">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('New Extraction Job') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Collection <span class="text-danger">*</span></label>
                        <select id="jobCollectionSelect" name="collection_id" required></select>
                        <small class="text-muted">{{ __('Search for a collection to extract from.') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Extraction Type') }}</label>
                        <select name="extraction_type" class="form-select">
                            <option value="ner">{{ __('Named Entity Recognition (NER)') }}</option>
                            <option value="ocr">{{ __('Optical Character Recognition (OCR)') }}</option>
                            <option value="summarize">{{ __('Summarize') }}</option>
                            <option value="translate">{{ __('Translate') }}</option>
                            <option value="spellcheck">{{ __('Spellcheck') }}</option>
                            <option value="face_detection">{{ __('Face Detection') }}</option>
                            <option value="form_extraction">{{ __('Form Extraction') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Language (optional)') }}</label>
                        <input type="text" name="language" class="form-control" placeholder="{{ __('en') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Model (optional)') }}</label>
                        <input type="text" name="model" class="form-control" placeholder="{{ __('default') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-robot me-1"></i>{{ __('Create Job') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // TomSelect for collection lookup
    var el = document.getElementById('jobCollectionSelect');
    if (el && typeof TomSelect !== 'undefined') {
        new TomSelect(el, {
            valueField: 'id',
            labelField: 'name',
            searchField: ['name'],
            placeholder: 'Type to search collections...',
            load: function(query, callback) {
                if (!query.length || query.length < 2) return callback();
                fetch('{{ url("informationobject/autocomplete") }}?query=' + encodeURIComponent(query) + '&limit=15')
                    .then(function(r) { return r.json(); })
                    .then(function(data) { callback(data); })
                    .catch(function() { callback(); });
            },
            render: {
                option: function(item, escape) {
                    return '<div>' + escape(item.name) + (item.slug ? '<small class="text-muted ms-2">' + escape(item.slug) + '</small>' : '') + '</div>';
                },
                item: function(item, escape) {
                    return '<div><i class="fas fa-folder me-1"></i>' + escape(item.name) + '</div>';
                }
            }
        });
    }

    // Auto-poll running/queued jobs
    var hasRunning = document.querySelectorAll('tr[data-status="running"], tr[data-status="queued"]').length > 0;
    if (hasRunning) {
        setTimeout(function() { location.reload(); }, 10000);
    }
});
</script>
@endsection
