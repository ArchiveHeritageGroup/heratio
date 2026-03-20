@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-file-alt me-2"></i>Research Reports</h1>@endsection
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <form method="GET" class="d-inline-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="draft" {{ ($currentStatus ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="in_progress" {{ ($currentStatus ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="completed" {{ ($currentStatus ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="published" {{ ($currentStatus ?? '') === 'published' ? 'selected' : '' }}>Published</option>
            </select>
        </form>
    </div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#newReportModal"><i class="fas fa-plus me-1"></i>New Report</button>
</div>

<div class="row">
@forelse($reports as $report)
<div class="col-md-6 mb-3">
    <div class="card h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <h5 class="card-title mb-1">
                    <a href="{{ route('research.viewReport', $report->id) }}" class="text-decoration-none">{{ e($report->title) }}</a>
                </h5>
                @php
                    $statusColors = ['draft' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'published' => 'primary'];
                    $statusColor = $statusColors[$report->status ?? 'draft'] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $report->status ?? 'draft')) }}</span>
            </div>
            @if($report->project_id ?? null)
                <small class="text-muted"><i class="fas fa-project-diagram me-1"></i>Project #{{ $report->project_id }}</small><br>
            @endif
            @if($report->report_type ?? null)
                <small class="text-muted"><i class="fas fa-tag me-1"></i>{{ ucfirst(str_replace('_', ' ', $report->report_type)) }}</small><br>
            @endif
            @if($report->description ?? null)
                <p class="card-text small text-muted mt-2 mb-0">{{ e(\Illuminate\Support\Str::limit($report->description, 120)) }}</p>
            @endif
            <div class="mt-2">
                <small class="text-muted"><i class="fas fa-calendar me-1"></i>Created: {{ \Carbon\Carbon::parse($report->created_at)->format('j M Y') }}</small>
            </div>
        </div>
    </div>
</div>
@empty
<div class="col-12 text-center text-muted py-4">
    <i class="fas fa-file-alt fa-3x mb-3 d-block"></i>
    No reports yet. Create a report to document your research findings.
</div>
@endforelse
</div>

{{-- New Report Modal --}}
<div class="modal fade" id="newReportModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('research.reports') }}">@csrf<input type="hidden" name="form_action" value="create">
    <div class="modal-header"><h5 class="modal-title">New Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="title" required></div>
        <div class="mb-3"><label class="form-label">Project ID</label><input type="number" class="form-control" name="project_id" placeholder="Link to a project (optional)"></div>
        <div class="mb-3"><label class="form-label">Report Type</label>
            <select name="report_type" class="form-select">
                <option value="progress">Progress Report</option>
                <option value="final">Final Report</option>
                <option value="literature_review">Literature Review</option>
                <option value="methodology">Methodology</option>
                <option value="findings">Findings</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Create Report</button></div>
    </form>
</div></div></div>
@endsection
