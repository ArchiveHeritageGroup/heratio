{{-- Annotation Studio - Migrated from AtoM: annotationStudioSuccess.php --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'annotations'])
@endsection

@section('title', 'Annotation Studio')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        @if(!empty($objectSlug))
            <li class="breadcrumb-item"><a href="/{{ e($objectSlug) }}">{{ e($objectTitle ?? '') }}</a></li>
        @endif
        <li class="breadcrumb-item active">Annotation Studio</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Annotation Studio: {{ e($objectTitle ?? '') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.annotations', ['export_iiif' => 1, 'object_id' => $objectId ?? 0]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>{{ __('Export IIIF') }}</a>
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#importIiifModal"><i class="fas fa-file-import me-1"></i>{{ __('Import IIIF') }}</button>
        @if(!empty($objectSlug))
            <a href="/{{ e($objectSlug) }}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-external-link-alt me-1"></i>{{ __('View Record') }}</a>
        @endif
    </div>
</div>

@php
    $has3D = !empty($has3DModel) && !empty($model3D);
    $hasImage = !empty($imageUrl);
    $defaultMode = ($has3D && !$hasImage) ? '3d' : 'image';
@endphp

@if($has3D && $hasImage)
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link {{ $defaultMode === 'image' ? 'active' : '' }}" data-bs-toggle="tab" href="#imageTab">Image</a></li>
    <li class="nav-item"><a class="nav-link {{ $defaultMode === '3d' ? 'active' : '' }}" data-bs-toggle="tab" href="#threeDTab">3D Model</a></li>
</ul>
@endif

<div class="row">
    <div class="col-md-8">
        {{-- Image Viewer / 3D Viewer --}}
        <div class="card mb-4">
            <div class="card-body p-0">
                @if($hasImage)
                <div id="openseadragon-container" style="width:100%;height:500px;background:#1a1a2e;"></div>
                @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-image fa-3x mb-3 opacity-50"></i>
                    <p>No digital object available for annotation.</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Annotations List --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-sticky-note me-2"></i>Annotations ({{ count($annotations ?? []) }})</span>
                <button class="btn btn-sm atom-btn-white" id="addAnnotationBtn"><i class="fas fa-plus me-1"></i>{{ __('Add Annotation') }}</button>
            </div>
            <div class="card-body p-0">
                @if(!empty($annotations))
                <div class="list-group list-group-flush">
                    @foreach($annotations as $ann)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>{{ e($ann->title ?? 'Untitled') }}</strong>
                                @if($ann->annotation_type ?? false)
                                    <span class="badge bg-secondary ms-1">{{ ucfirst($ann->annotation_type) }}</span>
                                @endif
                                <br><small class="text-muted">{{ e(($ann->first_name ?? '') . ' ' . ($ann->last_name ?? '')) }} &middot; {{ $ann->created_at ?? '' }}</small>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary edit-ann-btn" data-id="{{ $ann->id }}"><i class="fas fa-edit"></i></button>
                                <form method="POST" action="{{ route('research.annotations.destroy', $ann->id) }}" class="d-inline" onsubmit="return confirm('Delete this annotation?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        @if($ann->content ?? false)
                            <p class="mb-0 mt-1 small">{{ e(Str::limit($ann->content, 200)) }}</p>
                        @endif
                        @if($ann->tags ?? false)
                            <div class="mt-1">
                                @foreach(explode(',', $ann->tags) as $tag)
                                    <span class="badge bg-light text-dark">{{ trim($tag) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-sticky-note fa-2x mb-2 opacity-50"></i>
                    <p>No annotations yet. Click "Add Annotation" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Annotation Form --}}
        <div class="card mb-4" id="annotationForm" style="display:none;">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-edit me-2"></i>New Annotation</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.annotations.store') }}">
                    @csrf
                    <input type="hidden" name="object_id" value="{{ $objectId ?? 0 }}">
                    <input type="hidden" name="target_selector" id="targetSelector" value="">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <select name="annotation_type" class="form-select form-select-sm">
                            <option value="comment">{{ __('Comment') }}</option>
                            <option value="transcription">{{ __('Transcription') }}</option>
                            <option value="translation">{{ __('Translation') }}</option>
                            <option value="description">{{ __('Description') }}</option>
                            <option value="question">{{ __('Question') }}</option>
                            <option value="correction">{{ __('Correction') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <textarea name="content" class="form-control form-control-sm" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <input type="text" name="tags" class="form-control form-control-sm" placeholder="{{ __('comma-separated') }}">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn atom-btn-white btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                        <button type="button" class="btn atom-btn-white btn-sm" id="cancelAnnotation">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Layers Panel --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Layers</h6></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-image me-2 text-primary"></i>{{ __('Base Image') }}</span>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked disabled></div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-sticky-note me-2 text-warning"></i>{{ __('Annotations') }}</span>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked id="toggleAnnotations"></div>
                </li>
            </ul>
        </div>

        {{-- Object Info --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Object Info</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Title</dt><dd class="col-sm-8">{{ e($objectTitle ?? 'N/A') }}</dd>
                    <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ e($objectType ?? 'N/A') }}</dd>
                    @if(!empty($objectIdentifier))
                    <dt class="col-sm-4">ID</dt><dd class="col-sm-8">{{ e($objectIdentifier) }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

{{-- Import IIIF Modal --}}
<div class="modal fade" id="importIiifModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('Import IIIF Annotations') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">IIIF Annotation List URL or JSON <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <textarea id="iiifImportData" class="form-control" rows="6" placeholder="{{ __('Paste IIIF annotation list JSON or URL...') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn atom-btn-white" id="importIiifBtn"><i class="fas fa-file-import me-1"></i>{{ __('Import') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addBtn = document.getElementById('addAnnotationBtn');
    var cancelBtn = document.getElementById('cancelAnnotation');
    var form = document.getElementById('annotationForm');
    if (addBtn && form) {
        addBtn.addEventListener('click', function() { form.style.display = 'block'; });
    }
    if (cancelBtn && form) {
        cancelBtn.addEventListener('click', function() { form.style.display = 'none'; });
    }
});
</script>
@endsection
