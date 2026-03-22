{{-- Ethics Milestones - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Ethics Milestones')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Ethics Milestones</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Ethics Milestones</h1>
    <button class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#addMilestoneModal"><i class="fas fa-plus me-1"></i> Add Milestone</button>
</div>
@if(empty($milestones))
    <div class="alert alert-info">No ethics milestones yet. Add one to track your ethics review process.</div>
@else
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light"><tr><th>#</th><th>Title</th><th>Type</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($milestones as $i => $m)
            <tr>
                <td>{{ (int)($m->sort_order ?? $i + 1) }}</td>
                <td><strong>{{ e($m->title ?? '') }}</strong>@if($m->description ?? false)<br><small class="text-muted">{{ e(Str::limit($m->description, 100)) }}</small>@endif</td>
                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $m->milestone_type ?? '')) }}</span></td>
                <td><span class="badge bg-{{ match($m->status ?? '') { 'completed' => 'success', 'in_progress' => 'primary', 'blocked' => 'danger', default => 'warning' } }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}</span></td>
                <td class="small">{{ $m->created_at ?? '' }}</td>
                <td>
                    <form method="POST" class="d-inline">@csrf <input type="hidden" name="milestone_id" value="{{ $m->id }}"><input type="hidden" name="action" value="toggle_status">
                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-sync"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
<div class="modal fade" id="addMilestoneModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">@csrf <input type="hidden" name="action" value="add_milestone"><input type="hidden" name="project_id" value="{{ $project->id ?? 0 }}">
    <div class="modal-header"><h5 class="modal-title">Add Ethics Milestone</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="milestone_type" class="form-select"><option value="ethics_approval">Ethics Approval</option><option value="irb_submission">IRB Submission</option><option value="consent_collection">Consent Collection</option><option value="data_management_plan">Data Management Plan</option><option value="other">Other</option></select></div>
        <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-white">Add</button></div>
</form></div></div></div>
@endsection