{{-- Edit Document Template - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'documentTemplates'])@endsection
@section('title', ($isNew ?? true) ? 'New Document Template' : 'Edit Document Template')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.documentTemplates') }}">Document Templates</a></li><li class="breadcrumb-item active">{{ ($isNew ?? true) ? 'New' : 'Edit' }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-file-alt text-primary me-2"></i>{{ ($isNew ?? true) ? 'New Document Template' : 'Edit Document Template' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-8"><label class="form-label">Template Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="name" class="form-control" required value="{{ e($template->name ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Category <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="category" class="form-select">
                        <option value="letter" {{ ($template->category ?? '') === 'letter' ? 'selected' : '' }}>Letter</option>
                        <option value="report" {{ ($template->category ?? '') === 'report' ? 'selected' : '' }}>Report</option>
                        <option value="form" {{ ($template->category ?? '') === 'form' ? 'selected' : '' }}>Form</option>
                        <option value="certificate" {{ ($template->category ?? '') === 'certificate' ? 'selected' : '' }}>Certificate</option>
                        <option value="other" {{ ($template->category ?? '') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Description <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><textarea name="description" class="form-control" rows="2">{{ e($template->description ?? '') }}</textarea></div>
            <div class="mb-3"><label class="form-label">Template Content <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><textarea name="content" class="form-control font-monospace" rows="15" required>{{ e($template->content ?? '') }}</textarea>
                <small class="text-muted">Use placeholders: {researcher_name}, {date}, {institution}, {reference_number}</small>
            </div>
            <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="isActive" {{ ($template->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label" for="isActive">Active <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save Template') }}</button>
            <a href="{{ route('research.documentTemplates') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection