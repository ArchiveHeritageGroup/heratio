{{-- Extraction Jobs - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Extraction Jobs')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Extraction Jobs</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-cogs text-primary me-2"></i>Extraction Jobs</h1>
    <button class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#newJobModal"><i class="fas fa-plus me-1"></i>New Job</button>
</div>
@if(!empty($jobs))
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Job</th><th>Source</th><th>Type</th><th>Status</th><th>Progress</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @foreach($jobs as $job)
                <tr>
                    <td><strong>{{ e($job->title ?? 'Job #' . $job->id) }}</strong></td>
                    <td>{{ e($job->source_name ?? '-') }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $job->extraction_type ?? '')) }}</span></td>
                    <td><span class="badge bg-{{ match($job->status ?? '') { 'completed' => 'success', 'running' => 'primary', 'failed' => 'danger', 'queued' => 'info', default => 'warning' } }}">{{ ucfirst($job->status ?? 'pending') }}</span></td>
                    <td>
                        <div class="progress" style="height:6px;"><div class="progress-bar" style="width:{{ $job->progress ?? 0 }}%"></div></div>
                        <small>{{ $job->progress ?? 0 }}%</small>
                    </td>
                    <td class="small">{{ $job->created_at ?? '' }}</td>
                    <td><a href="{{ route('research.dashboard', ['view_extraction_job' => $job->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-info">No extraction jobs found.</div>
@endif
<div class="modal fade" id="newJobModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">@csrf
    <div class="modal-header"><h5 class="modal-title">New Extraction Job</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Extraction Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="extraction_type" class="form-select"><option value="ocr">OCR</option><option value="ner">Named Entity Recognition</option><option value="metadata">Metadata Extraction</option><option value="full_text">Full Text</option></select></div>
        <div class="mb-3"><label class="form-label">Source Collection <span class="badge bg-secondary ms-1">Optional</span></label><select name="collection_id" class="form-select"><option value="">-- Select --</option>@foreach($collections ?? [] as $c)<option value="{{ $c->id }}">{{ e($c->name ?? '') }}</option>@endforeach</select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-white">Start Job</button></div>
</form></div></div></div>
@endsection