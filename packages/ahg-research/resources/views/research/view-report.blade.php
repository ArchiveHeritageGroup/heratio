@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-file-alt me-2"></i>{{ e($report->title) }}</h1>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-1">{{ e($report->title) }}</h5>
                @if($report->description ?? null)<p class="text-muted mb-1">{{ e($report->description) }}</p>@endif
                <div class="d-flex flex-wrap gap-3 mt-2">
                    @if($report->report_type ?? null)
                    <small class="text-muted"><i class="fas fa-tag me-1"></i>Type: <strong>{{ ucfirst(str_replace('_', ' ', $report->report_type)) }}</strong></small>
                    @endif
                    @if($report->project_id ?? null)
                    <small class="text-muted"><i class="fas fa-project-diagram me-1"></i>Project #{{ $report->project_id }}</small>
                    @endif
                    <small class="text-muted"><i class="fas fa-calendar me-1"></i>Created: {{ \Carbon\Carbon::parse($report->created_at)->format('j M Y') }}</small>
                    @if($report->updated_at ?? null)
                    <small class="text-muted"><i class="fas fa-edit me-1"></i>Updated: {{ \Carbon\Carbon::parse($report->updated_at)->format('j M Y') }}</small>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 align-items-start">
                @php
                    $statusColors = ['draft' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'published' => 'primary'];
                    $statusColor = $statusColors[$report->status ?? 'draft'] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst(str_replace('_', ' ', $report->status ?? 'draft')) }}</span>
            </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2">
            <button class="btn atom-btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal"><i class="fas fa-plus me-1"></i>Add Section</button>
            <button class="btn atom-btn-white btn-sm" data-bs-toggle="modal" data-bs-target="#changeStatusModal"><i class="fas fa-exchange-alt me-1"></i>Change Status</button>
            <a href="{{ route('research.viewReport', $report->id) }}?export=1" class="btn atom-btn-white btn-sm"><i class="fas fa-file-export me-1"></i>Export</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this report and all its sections?')">
                @csrf
                <input type="hidden" name="form_action" value="delete_report">
                <button type="submit" class="btn atom-btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete Report</button>
            </form>
        </div>
    </div>
</div>

{{-- Report Sections --}}
<h5 class="mb-3"><i class="fas fa-list-ol me-2"></i>Sections ({{ count($report->sections) }})</h5>

@forelse($report->sections as $section)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h6 class="mb-0"><i class="fas fa-paragraph me-2"></i>{{ e($section->title ?? 'Untitled Section') }}</h6>
        <div class="btn-group btn-group-sm">
            <button class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#editSection{{ $section->id }}"><i class="fas fa-edit"></i></button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section?')">
                @csrf
                <input type="hidden" name="form_action" value="delete_section">
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-times"></i></button>
            </form>
        </div>
    </div>
    @if($section->content ?? null)
    <div class="card-body">
        @if(($section->content_format ?? 'text') === 'html')
            {!! $section->content !!}
        @else
            <p>{{ e($section->content) }}</p>
        @endif
    </div>
    @else
    <div class="card-body text-muted fst-italic">No content yet. Click edit to add content.</div>
    @endif
</div>

{{-- Edit Section Modal --}}
<div class="modal fade" id="editSection{{ $section->id }}" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="update_section"><input type="hidden" name="section_id" value="{{ $section->id }}">
    <div class="modal-header"><h5 class="modal-title">Edit Section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="title" value="{{ e($section->title ?? '') }}"></div>
        <div class="mb-3"><label class="form-label">Content <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="content" rows="10">{{ e($section->content ?? '') }}</textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save Section</button></div>
    </form>
</div></div></div>
@empty
<div class="text-center text-muted py-4">
    <i class="fas fa-paragraph fa-3x mb-3 d-block"></i>
    No sections yet. Add a section to start building your report.
</div>
@endforelse

<div class="mt-3">
    <a href="{{ route('research.reports') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Reports</a>
</div>

{{-- Add Section Modal --}}
<div class="modal fade" id="addSectionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="add_section">
    <div class="modal-header"><h5 class="modal-title">Add Section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Section Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" name="title" required></div>
        <div class="mb-3"><label class="form-label">Section Type <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="section_type" class="form-select">
                <option value="text">Text</option>
                <option value="introduction">Introduction</option>
                <option value="methodology">Methodology</option>
                <option value="findings">Findings</option>
                <option value="conclusion">Conclusion</option>
                <option value="bibliography">Bibliography</option>
                <option value="appendix">Appendix</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Add Section</button></div>
    </form>
</div></div></div>

{{-- Change Status Modal --}}
<div class="modal fade" id="changeStatusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="update_status">
    <div class="modal-header"><h5 class="modal-title">Change Report Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="status" class="form-select">
                <option value="draft" {{ ($report->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="in_progress" {{ ($report->status ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="completed" {{ ($report->status ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="published" {{ ($report->status ?? '') === 'published' ? 'selected' : '' }}>Published</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Update Status</button></div>
    </form>
</div></div></div>
@endsection
