{{-- Ethics Milestones --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Ethics Milestones')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Ethics Milestones</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-balance-scale text-primary me-2"></i>Ethics Milestones</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal"><i class="fas fa-plus me-1"></i>Add Milestone</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

@if(!empty($milestones) && count($milestones) > 0)
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($milestones as $i => $m)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ e($m->title ?? '') }}</strong>
                            @if($m->description ?? false)
                                <br><small class="text-muted">{{ e(Str::limit($m->description, 100)) }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $m->milestone_type ?? '')) }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ match($m->status ?? '') { 'approved' => 'success', 'completed' => 'success', 'rejected' => 'danger', default => 'warning' } }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}</span>
                        </td>
                        <td class="small">{{ $m->created_at ?? '' }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                @if(($m->status ?? '') !== 'approved')
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="approve">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                                </form>
                                @endif
                                @if(($m->status ?? '') !== 'rejected')
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="reject">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                </form>
                                @endif
                                @if(($m->status ?? '') !== 'completed')
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="complete">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-primary" title="Complete"><i class="fas fa-flag-checkered"></i></button>
                                </form>
                                @endif
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this milestone?')">
                                    @csrf
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<div class="alert alert-info">No ethics milestones yet. Add one to track your ethics review process.</div>
@endif

{{-- Add Milestone Modal --}}
<div class="modal fade" id="addMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add Ethics Milestone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="milestone_type" class="form-select">
                            <option value="ethics">Ethics</option>
                            <option value="irb_approval">IRB Approval</option>
                            <option value="consent">Consent</option>
                            <option value="data_management">Data Management</option>
                            <option value="risk_assessment">Risk Assessment</option>
                            <option value="compliance_check">Compliance Check</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
