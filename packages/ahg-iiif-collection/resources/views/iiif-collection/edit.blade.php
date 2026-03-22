@extends('theme::layouts.2col')

@section('sidebar')
<div class="sidebar-content">
    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
        </div>
        <div class="card-body">
            <a href="{{ route('iiif-collection.view', $collection->id) }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-eye me-2"></i>View Collection
            </a>
            <a href="{{ route('iiif-collection.add-items', $collection->id) }}" class="btn atom-btn-outline-success w-100">
                <i class="fas fa-plus me-2"></i>Add Items
            </a>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-edit me-2"></i>Edit Collection</h1>
<h2>{{ e($collection->display_name) }}</h2>
@endsection

@section('content')
<div class="iiif-collection-form">
    <form method="POST" action="{{ route('iiif-collection.update', $collection->id) }}">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Collection Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ e($collection->name) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="parent_id">Parent Collection <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">— None (Top Level) —</option>
                                @foreach($allCollections as $col)
                                    @if($col->id != $collection->id)
                                    <option value="{{ $col->id }}" {{ $collection->parent_id == $col->id ? 'selected' : '' }}>
                                        {{ e($col->display_name) }}
                                    </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="description">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                    <textarea class="form-control" id="description" name="description" rows="3">{{ e($collection->description) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="attribution">Attribution <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" class="form-control" id="attribution" name="attribution" value="{{ e($collection->attribution) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="viewing_hint">Viewing Hint <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="viewing_hint" name="viewing_hint">
                                <option value="individuals" {{ $collection->viewing_hint == 'individuals' ? 'selected' : '' }}>Individuals</option>
                                <option value="paged" {{ $collection->viewing_hint == 'paged' ? 'selected' : '' }}>Paged</option>
                                <option value="continuous" {{ $collection->viewing_hint == 'continuous' ? 'selected' : '' }}>Continuous</option>
                                <option value="multi-part" {{ $collection->viewing_hint == 'multi-part' ? 'selected' : '' }}>Multi-part</option>
                                <option value="top" {{ $collection->viewing_hint == 'top' ? 'selected' : '' }}>Top</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" {{ $collection->is_public ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_public">
                            Public
                         <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
            <a href="{{ route('iiif-collection.view', $collection->id) }}" class="btn atom-btn-white">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </form>
</div>
@endsection
