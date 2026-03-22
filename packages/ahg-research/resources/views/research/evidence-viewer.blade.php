{{-- Evidence Viewer - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'collections'])@endsection
@section('title', 'Evidence Viewer')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.collections') }}">Evidence Sets</a></li><li class="breadcrumb-item active">Evidence Viewer</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-search-plus text-primary me-2"></i>Evidence Viewer</h1>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between"><span>Source Document</span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="zoomIn"><i class="fas fa-search-plus"></i></button>
                    <button class="btn btn-outline-secondary" id="zoomOut"><i class="fas fa-search-minus"></i></button>
                    <button class="btn btn-outline-secondary" id="zoomReset"><i class="fas fa-expand"></i></button>
                </div>
            </div>
            <div class="card-body p-0" style="min-height:500px;background:#f8f9fa;">
                @if(!empty($imageUrl))
                    <img src="{{ $imageUrl }}" class="img-fluid w-100" alt="Evidence document" id="evidenceImage">
                @else
                    <div class="text-center py-5 text-muted"><i class="fas fa-file-image fa-3x mb-3 opacity-50"></i><p>No image available.</p></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Source Info</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Title</dt><dd class="col-sm-8">{{ e($source->title ?? 'N/A') }}</dd>
                    <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ e($source->source_type ?? 'N/A') }}</dd>
                    <dt class="col-sm-4">Date</dt><dd class="col-sm-8">{{ e($source->date ?? 'N/A') }}</dd>
                    <dt class="col-sm-4">Repository</dt><dd class="col-sm-8">{{ e($source->repository ?? 'N/A') }}</dd>
                </dl>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h6></div>
            <div class="card-body">
                <form method="POST">@csrf
                    <textarea name="notes" class="form-control mb-2" rows="4">{{ e($notes ?? '') }}</textarea>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Save Notes</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-tags me-2"></i>Tags</h6></div>
            <div class="card-body">
                @foreach($tags ?? [] as $tag)<span class="badge bg-light text-dark me-1 mb-1">{{ e($tag) }}</span>@endforeach
                @if(empty($tags))<span class="text-muted small">No tags</span>@endif
            </div>
        </div>
    </div>
</div>
@endsection