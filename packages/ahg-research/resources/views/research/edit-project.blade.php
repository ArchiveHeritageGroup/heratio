{{-- Edit Project - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', ($isNew ?? false) ? 'New Project' : 'Edit Project')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.projects') }}">Projects</a></li><li class="breadcrumb-item active">{{ ($isNew ?? false) ? 'New' : 'Edit' }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-project-diagram text-primary me-2"></i>{{ ($isNew ?? false) ? 'New Project' : 'Edit Project' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="title" class="form-control" required value="{{ e($project->title ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="project_type" class="form-select">
                        @foreach(['genealogical','historical','academic','personal','institutional','other'] as $t)
                        <option value="{{ $t }}" {{ ($project->project_type ?? '') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="description" class="form-control" rows="4">{{ e($project->description ?? '') }}</textarea></div>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">Status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="status" class="form-select">
                        @foreach(['planning','active','on_hold','completed','archived'] as $s)
                        <option value="{{ $s }}" {{ ($project->status ?? 'planning') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Start Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="date" name="start_date" class="form-control" value="{{ $project->start_date ?? '' }}"></div>
                <div class="col-md-4"><label class="form-label">End Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="date" name="end_date" class="form-control" value="{{ $project->end_date ?? '' }}"></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Institution <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="institution_id" class="form-select"><option value="">-- None --</option>
                        @foreach($institutions ?? [] as $inst)<option value="{{ $inst->id }}" {{ ($project->institution_id ?? '') == $inst->id ? 'selected' : '' }}>{{ e($inst->name) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Visibility <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="visibility" class="form-select">
                        <option value="private" {{ ($project->visibility ?? 'private') === 'private' ? 'selected' : '' }}>Private</option>
                        <option value="team" {{ ($project->visibility ?? '') === 'team' ? 'selected' : '' }}>Team</option>
                        <option value="public" {{ ($project->visibility ?? '') === 'public' ? 'selected' : '' }}>Public</option>
                    </select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Tags <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" name="tags" class="form-control" value="{{ e($project->tags ?? '') }}" placeholder="{{ __('comma-separated') }}"></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save Project') }}</button>
            <a href="{{ route('research.projects') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection