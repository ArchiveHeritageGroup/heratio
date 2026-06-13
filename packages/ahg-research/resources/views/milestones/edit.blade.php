{{-- Research Milestones & Deliverables tracker - create / edit form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@php $isEdit = ! empty($milestone); @endphp

@section('title', $isEdit ? __('Edit Milestone') : __('Add Milestone'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.milestones.index', $project->id ?? 0) }}">{{ __('Milestones & Deliverables') }}</a></li>
        <li class="breadcrumb-item active">{{ $isEdit ? __('Edit') : __('New') }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-flag-checkered text-primary me-2"></i>{{ $isEdit ? __('Edit Milestone') : __('Add Milestone') }}</h1>
    <a href="{{ route('research.milestones.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $isEdit ? route('research.milestones.update', [$project->id ?? 0, $milestone['id']]) : route('research.milestones.store', $project->id ?? 0) }}" autocomplete="off">
    @csrf
    @if($isEdit)@method('PUT')@endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Milestone') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="512" required value="{{ old('title', $milestone['title'] ?? '') }}" placeholder="{{ __('name of the milestone or deliverable') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
                    <select name="milestone_type" class="form-select" required>
                        @foreach($typeOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('milestone_type', $milestone['milestone_type'] ?? 'milestone') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('A milestone is a planned point in the work; a deliverable is a tangible output the plan commits to.') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Deliverable') }}</label>
                    <input type="text" name="deliverable" class="form-control" maxlength="512" value="{{ old('deliverable', $milestone['deliverable'] ?? '') }}" placeholder="{{ __('the concrete output expected at this milestone, if any') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="4" maxlength="65000" placeholder="{{ __('what this milestone or deliverable involves') }}">{{ old('description', $milestone['description'] ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Schedule & progress') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $milestone['status'] ?? 'planned') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Due date') }}</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $milestone['due_date'] ?? '') }}">
                    <div class="form-text">{{ __('A past due date on an open milestone is flagged as overdue; a date within 30 days is flagged as due soon.') }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Completed date') }}</label>
                    <input type="date" name="completed_date" class="form-control" value="{{ old('completed_date', $milestone['completed_date'] ?? '') }}">
                    <div class="form-text">{{ __('Set automatically to today when status is Completed and left blank.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Progress') }} ({{ __('0-100%') }})</label>
                    <input type="number" name="progress_pct" class="form-control" min="0" max="100" step="1" value="{{ old('progress_pct', $milestone['progress_pct'] ?? 0) }}">
                    <div class="form-text">{{ __('A Completed milestone is recorded at 100%.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save milestone') : __('Add milestone') }}</button>
        <a href="{{ route('research.milestones.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
