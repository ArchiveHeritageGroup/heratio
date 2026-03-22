@extends('theme::layouts.2col')

@section('sidebar')
<div class="sidebar-content">
    <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>IIIF Collections</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Organize and group related IIIF manifests into collections for easy browsing and discovery.</p>
            @auth
            <a href="{{ route('iiif-collection.create', ['parent_id' => $parentId]) }}" class="btn atom-btn-outline-success w-100">
                <i class="fas fa-plus me-2"></i>Create Collection
            </a>
            @endauth
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1>
    <i class="fas fa-layer-group me-2"></i>
    @if($parentCollection)
        {{ e($parentCollection->display_name) }}
    @else
        IIIF Collections
    @endif
</h1>
@endsection

@section('content')
<div class="iiif-collections">
    @if($parentCollection)
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('iiif-collection.index') }}">Collections</a></li>
            <li class="breadcrumb-item active">{{ e($parentCollection->display_name) }}</li>
        </ol>
    </nav>
    @endif

    @if(empty($collections))
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        No collections found.
        @auth
        <a href="{{ route('iiif-collection.create') }}">Create your first collection</a>
        @endauth
    </div>
    @else
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        @foreach($collections as $collection)
        <div class="col">
            <div class="card h-100 collection-card">
                @if($collection->thumbnail_url)
                <img src="{{ e($collection->thumbnail_url) }}" class="card-img-top" alt="{{ e($collection->display_name) }}" style="height: 150px; object-fit: cover;">
                @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                    <i class="fas fa-layer-group fa-4x text-muted"></i>
                </div>
                @endif
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="{{ route('iiif-collection.view', $collection->id) }}">
                            {{ e($collection->display_name) }}
                        </a>
                    </h5>
                    @if($collection->display_description)
                    <p class="card-text text-muted small">{{ e(mb_substr($collection->display_description, 0, 100)) }}...</p>
                    @endif
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                    <span class="badge bg-secondary">
                        <i class="fas fa-images me-1"></i>{{ $collection->item_count }} items
                    </span>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('iiif-collection.manifest', $collection->slug) }}" class="btn atom-btn-white" title="IIIF Manifest" target="_blank">
                            <i class="fas fa-code"></i>
                        </a>
                        @auth
                        <a href="{{ route('iiif-collection.edit', $collection->id) }}" class="btn atom-btn-white" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
