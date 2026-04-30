{{-- New Report - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reports'])@endsection
@section('title', 'New Report')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.reports') }}">Reports</a></li><li class="breadcrumb-item active">New Report</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-file-alt text-primary me-2"></i>{{ __('New Report') }}</h1>
<div class="card"><div class="card-body">
    <form method="POST">@csrf
        <div class="row mb-3">
            <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Template <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select name="template_type" class="form-select">
                <option value="custom">{{ __('Custom') }}</option><option value="research_summary">{{ __('Research Summary') }}</option><option value="genealogical">{{ __('Genealogical Report') }}</option><option value="historical">{{ __('Historical Analysis') }}</option><option value="source_analysis">{{ __('Source Analysis') }}</option><option value="finding_aid">{{ __('Finding Aid') }}</option>
            </select></div>
        </div>
        <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Project <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select name="project_id" class="form-select"><option value="">-- No project --</option>
            @foreach($projects ?? [] as $p)<option value="{{ $p->id }}">{{ e($p->title ?? '') }}</option>@endforeach
        </select></div>
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Create Report') }}</button>
        <a href="{{ route('research.reports') }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
@endsection