{{-- Ethics Milestones --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Ethics Milestones')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
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
    <h1 class="h2"><i class="fas fa-balance-scale text-primary me-2"></i>{{ __('Ethics Milestones') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal"><i class="fas fa-plus me-1"></i>{{ __('Add Milestone') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
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
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th>{{ __('Actions') }}</th>
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
                            <span class="badge bg-{{ match($m->status ?? '') { 'approved' => 'success', 'completed' => 'success', 'rejected' => 'danger', default => 'warning' } }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}</span>
                        </td>
                        <td class="small">{{ $m->due_date ?? '—' }}</td>
                        <td class="small">{{ $m->created_at ?? '' }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-secondary edit-milestone-btn" title="{{ __('Edit') }}"
                                    data-id="{{ $m->id }}"
                                    data-title="{{ e($m->title ?? '') }}"
                                    data-description="{{ e($m->description ?? '') }}"
                                    data-due_date="{{ $m->due_date ?? '' }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if(!in_array($m->status ?? '', ['approved', 'completed']))
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="approve">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-success" title="{{ __('Approve') }}"><i class="fas fa-check"></i></button>
                                </form>
                                @endif
                                @if(($m->status ?? '') !== 'rejected')
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="reject">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-warning" title="{{ __('Reject') }}"><i class="fas fa-times"></i></button>
                                </form>
                                @endif
                                @if(($m->status ?? '') !== 'completed')
                                <form method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="form_action" value="complete">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-primary" title="{{ __('Complete') }}"><i class="fas fa-flag-checkered"></i></button>
                                </form>
                                @endif
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this milestone?')">
                                    @csrf
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
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
                    <h5 class="modal-title">{{ __('Add Ethics Milestone') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Due Date') }}</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add Milestone') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Milestone Modal --}}
<div class="modal fade" id="editMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="milestone_id" id="edit-milestone-id">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Edit Milestone') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit-title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Due Date') }}</label>
                        <input type="date" name="due_date" id="edit-due-date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('js')
<script>
document.querySelectorAll('.edit-milestone-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit-milestone-id').value = this.dataset.id;
        document.getElementById('edit-title').value = this.dataset.title;
        document.getElementById('edit-description').value = this.dataset.description;
        document.getElementById('edit-due-date').value = this.dataset.due_date || '';
        new bootstrap.Modal(document.getElementById('editMilestoneModal')).show();
    });
});
</script>
@endpush
@endsection
