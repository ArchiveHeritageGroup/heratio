@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'annotations'])@endsection
@section('title', 'My Notes & Annotations')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>
        <a href="{{ route('research.workspace') }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-arrow-left"></i></a>
        <i class="fas fa-sticky-note text-warning me-2"></i>My Notes & Annotations
    </h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#exportNotesModal"><i class="fas fa-file-export me-1"></i>Export</button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#annotationModal"><i class="fas fa-plus me-1"></i>Add Note</button>
    </div>
</div>

{{-- Search + Visibility --}}
<div class="row mb-3">
    <div class="col-md-6">
        <form method="get" class="input-group input-group-sm">
            <input type="text" name="q" class="form-control" placeholder="Search notes..." value="{{ e($query ?? '') }}">
            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
            @if($query ?? '')
                <a href="{{ route('research.annotations') }}" class="btn btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('research.annotations') }}" class="btn btn-outline-secondary {{ empty($visibility ?? '') ? 'active' : '' }}">All</a>
            <a href="{{ route('research.annotations', ['visibility' => 'private']) }}" class="btn btn-outline-secondary {{ ($visibility ?? '') === 'private' ? 'active' : '' }}">Private</a>
            <a href="{{ route('research.annotations', ['visibility' => 'shared']) }}" class="btn btn-outline-secondary {{ ($visibility ?? '') === 'shared' ? 'active' : '' }}">Shared</a>
            <a href="{{ route('research.annotations', ['visibility' => 'public']) }}" class="btn btn-outline-secondary {{ ($visibility ?? '') === 'public' ? 'active' : '' }}">Public</a>
        </div>
    </div>
</div>

{{-- Tag Cloud --}}
@php
    $allTags = [];
    foreach (($annotations ?? []) as $ann) {
        if (!empty($ann->tags)) {
            foreach (array_map('trim', explode(',', $ann->tags)) as $t) {
                if ($t) $allTags[$t] = ($allTags[$t] ?? 0) + 1;
            }
        }
    }
    ksort($allTags);
    $activeTag = $tag ?? '';
@endphp
@if(!empty($allTags))
<div class="mb-3">
    <small class="text-muted me-2"><i class="fas fa-tags"></i> Tags:</small>
    <a href="{{ route('research.annotations', ['q' => $query ?? '']) }}" class="badge bg-{{ !$activeTag ? 'primary' : 'light text-dark' }} text-decoration-none me-1">All</a>
    @foreach($allTags as $tagName => $count)
        <a href="{{ route('research.annotations', ['tag' => $tagName, 'q' => $query ?? '']) }}" class="badge bg-{{ $activeTag === $tagName ? 'primary' : 'light text-dark' }} text-decoration-none me-1">{{ e($tagName) }} <small>({{ $count }})</small></a>
    @endforeach
</div>
@endif

{{-- Annotations Grid --}}
@if(!empty($annotations) && count($annotations) > 0)
<div class="row">
    @foreach($annotations as $ann)
    <div class="col-md-6 col-lg-4 mb-4" id="note-{{ $ann->id }}">
        <div class="card h-100">
            <div class="card-header bg-warning bg-opacity-25 d-flex justify-content-between align-items-center py-2">
                <div>
                    <strong><i class="fas fa-sticky-note text-warning me-1"></i>{{ e($ann->title ?: 'Untitled Note') }}</strong>
                    @if(($ann->visibility ?? 'private') !== 'private')
                        <span class="badge bg-{{ ($ann->visibility ?? '') === 'shared' ? 'info' : 'success' }} ms-1">{{ ucfirst($ann->visibility ?? 'private') }}</span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-sm btn-outline-primary py-0 px-1 edit-annotation"
                        data-id="{{ $ann->id }}"
                        data-title="{{ e($ann->title ?? '') }}"
                        data-content="{{ e($ann->content ?? '') }}"
                        data-object-id="{{ $ann->object_id ?? '' }}"
                        data-object-title="{{ e($ann->object_title ?? '') }}"
                        data-visibility="{{ $ann->visibility ?? 'private' }}"
                        data-tags="{{ e($ann->tags ?? '') }}"
                        title="Edit"><i class="fas fa-edit"></i></button>
                    <form method="POST" action="{{ route('research.annotations.destroy', $ann->id) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            @if(!empty($ann->thumbnail_path) && !empty($ann->object_id))
            <div class="text-center bg-light p-2">
                <a href="{{ url('/' . ($ann->object_slug ?? $ann->object_id)) }}" title="{{ e($ann->object_title ?? '') }}">
                    <img src="{{ $ann->thumbnail_path }}" alt="{{ e($ann->object_title ?? '') }}" class="img-fluid rounded" style="max-height:120px;">
                </a>
            </div>
            @endif
            <div class="card-body">
                <div class="card-text">
                    @if(($ann->content_format ?? 'text') === 'html')
                        {!! $ann->content !!}
                    @else
                        {!! nl2br(e($ann->content ?? '')) !!}
                    @endif
                </div>
                @if(!empty($ann->tags))
                <div class="mt-2">
                    @foreach(array_map('trim', explode(',', $ann->tags)) as $t)
                        @if($t)<a href="{{ route('research.annotations', ['tag' => $t]) }}" class="badge bg-secondary text-decoration-none me-1">{{ e($t) }}</a>@endif
                    @endforeach
                </div>
                @endif
            </div>
            <div class="card-footer bg-transparent small text-muted">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        @if(!empty($ann->collection_id) && !empty($ann->collection_name))
                            <a href="{{ route('research.viewCollection') }}?id={{ $ann->collection_id }}"><i class="fas fa-folder me-1"></i>{{ e($ann->collection_name) }}</a><br>
                        @endif
                        @if($ann->object_id ?? null)
                            @php
                                $entityIcon = 'archive';
                                $et = $ann->entity_type ?? 'information_object';
                                if ($et === 'actor') $entityIcon = 'user-tie';
                                elseif ($et === 'repository') $entityIcon = 'building';
                                elseif ($et === 'accession') $entityIcon = 'inbox';
                            @endphp
                            <a href="{{ url('/' . ($ann->object_slug ?? $ann->object_id)) }}"><i class="fas fa-{{ $entityIcon }} me-1"></i>{{ e(\Illuminate\Support\Str::limit($ann->object_title ?? 'View Entity', 30)) }}</a><br>
                        @endif
                        <i class="fas fa-clock me-1"></i>{{ date('M j, Y H:i', strtotime($ann->created_at)) }}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ url('/research/exportNotes?format=pdf&ids=' . $ann->id) }}" class="text-danger" title="Export PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="{{ url('/research/exportNotes?format=csv&ids=' . $ann->id) }}" class="text-success" title="Export CSV"><i class="fas fa-file-csv"></i></a>
                        <a href="#note-{{ $ann->id }}" class="text-muted" title="Permalink"><i class="fas fa-hashtag"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="text-center py-5">
    <i class="fas fa-sticky-note fa-4x text-muted mb-3 opacity-50"></i>
    <h4 class="text-muted">No notes yet</h4>
    <p class="text-muted">Add notes to items while browsing or create standalone notes here.</p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annotationModal"><i class="fas fa-plus me-1"></i>Create Your First Note</button>
</div>
@endif

{{-- Create/Edit Modal (shared) --}}
<div class="modal fade" id="annotationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('research.annotations.store') }}" method="POST" id="annotationForm">
                @csrf
                <input type="hidden" name="_method" id="annotationMethod" value="POST">
                <input type="hidden" name="annotation_id" id="annotationId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="annotationModalTitle"><i class="fas fa-sticky-note me-2"></i>New Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" id="annTitle" class="form-control" placeholder="Optional title..."></div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" id="annVisibility" class="form-select">
                            <option value="private">Private - only you</option>
                            <option value="shared">Shared - project collaborators</option>
                            <option value="public">Public - all researchers</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Note Content *</label><textarea name="content" id="annContent" class="form-control" rows="6" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Tags</label><input type="text" name="tags" id="annTags" class="form-control" placeholder="Comma-separated tags..."><small class="text-muted">e.g. genealogy, 19th century, photographs</small></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="annotationSubmitBtn"><i class="fas fa-save me-1"></i>Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Export Notes Modal --}}
<div class="modal fade" id="exportNotesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file-export me-2"></i>Export Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Notes to Export</label>
                    <div class="form-check mb-2 border-bottom pb-2">
                        <input class="form-check-input" type="checkbox" id="exportSelectAll" checked>
                        <label class="form-check-label fw-bold" for="exportSelectAll">Select All</label>
                    </div>
                    @if(!empty($annotations))
                    <div style="max-height:300px; overflow-y:auto;">
                        @foreach($annotations as $ann)
                        <div class="form-check mb-1">
                            <input class="form-check-input export-note-check" type="checkbox" value="{{ $ann->id }}" id="exportNote{{ $ann->id }}" checked>
                            <label class="form-check-label" for="exportNote{{ $ann->id }}">{{ e($ann->title ?: 'Untitled') }} <small class="text-muted">({{ date('M j', strtotime($ann->created_at)) }})</small></label>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Format</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger flex-fill export-btn" data-format="pdf"><i class="fas fa-file-pdf me-1"></i>PDF</button>
                        <button type="button" class="btn btn-outline-success flex-fill export-btn" data-format="csv"><i class="fas fa-file-csv me-1"></i>CSV</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('annotationForm');
    var isEdit = false;

    // Reset modal on open (for new)
    document.getElementById('annotationModal').addEventListener('show.bs.modal', function() {
        if (!isEdit) {
            form.action = '{{ route("research.annotations.store") }}';
            document.getElementById('annotationMethod').value = 'POST';
            document.getElementById('annotationId').value = '';
            document.getElementById('annTitle').value = '';
            document.getElementById('annContent').value = '';
            document.getElementById('annVisibility').value = 'private';
            document.getElementById('annTags').value = '';
            document.getElementById('annotationModalTitle').innerHTML = '<i class="fas fa-sticky-note me-2"></i>New Note';
            document.getElementById('annotationSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Save Note';
        }
        isEdit = false;
    });

    // Edit button
    document.querySelectorAll('.edit-annotation').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            isEdit = true;
            var d = this.dataset;
            form.action = '{{ url("/research/annotations") }}/' + d.id;
            document.getElementById('annotationMethod').value = 'PUT';
            document.getElementById('annotationId').value = d.id;
            document.getElementById('annTitle').value = d.title;
            document.getElementById('annContent').value = d.content;
            document.getElementById('annVisibility').value = d.visibility || 'private';
            document.getElementById('annTags').value = d.tags || '';
            document.getElementById('annotationModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Note';
            document.getElementById('annotationSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Update Note';
            new bootstrap.Modal(document.getElementById('annotationModal')).show();
        });
    });

    // Export: select all toggle
    var selectAll = document.getElementById('exportSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.export-note-check').forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }

    // Export buttons
    document.querySelectorAll('.export-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var checked = document.querySelectorAll('.export-note-check:checked');
            if (checked.length === 0) { alert('Select at least one note.'); return; }
            var ids = [];
            checked.forEach(function(cb) { ids.push(cb.value); });
            // Simple CSV export via redirect
            window.location.href = '/research/exportNotes?format=' + this.dataset.format + '&ids=' + ids.join(',');
            bootstrap.Modal.getInstance(document.getElementById('exportNotesModal')).hide();
        });
    });
});
</script>
@endpush
@endsection
