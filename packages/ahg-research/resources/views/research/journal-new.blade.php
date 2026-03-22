{{-- New Journal Entry - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'journal'])@endsection
@section('title', 'New Journal Entry')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.journal') }}">Journal</a></li><li class="breadcrumb-item active">New Entry</li></ol></nav>
<div class="row"><div class="col-md-8">
<div class="card"><div class="card-header"><h5 class="mb-0"><i class="fas fa-book me-2"></i>New Journal Entry</h5></div>
<div class="card-body">
    <form method="POST" id="entryForm">@csrf <input type="hidden" name="content" id="entryContentHidden" value="">
        <div class="row mb-3">
            <div class="col-md-8"><label class="form-label">Title</label><input type="text" name="title" class="form-control" placeholder="Entry title..." required></div>
            <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
        </div>
        <div class="mb-3"><label class="form-label">Project</label><select name="project_id" class="form-select"><option value="">-- No project --</option>
            @foreach($projects ?? [] as $p)<option value="{{ $p->id }}">{{ e($p->title ?? '') }}</option>@endforeach
        </select></div>
        <div class="mb-3"><label class="form-label">Entry Type</label><select name="entry_type" class="form-select"><option value="note">Note</option><option value="observation">Observation</option><option value="analysis">Analysis</option><option value="methodology">Methodology</option><option value="finding">Finding</option><option value="question">Question</option></select></div>
        <div class="mb-3"><label class="form-label">Content</label><div id="tiptapEditor" style="min-height:200px;border:1px solid #dee2e6;border-radius:0.375rem;padding:12px;"></div></div>
        <div class="mb-3"><label class="form-label">Tags</label><input type="text" name="tags" class="form-control" placeholder="comma-separated tags"></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Entry</button>
        <a href="{{ route('research.journal') }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
</div><div class="col-md-4">
<div class="card"><div class="card-header"><h6 class="mb-0">Tips</h6></div><div class="card-body small text-muted">
    <p>Use your research journal to record observations, ideas, and methodological notes as you work through your sources.</p>
    <p class="mb-0">Entries can be linked to projects and tagged for easy retrieval later.</p>
</div></div>
</div></div>
@endsection