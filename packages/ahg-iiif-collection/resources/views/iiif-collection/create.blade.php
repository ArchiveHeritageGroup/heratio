@extends('theme::layouts.2col')

@section('sidebar')
<div class="sidebar-content">
    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
        </div>
        <div class="card-body">
            <a href="{{ route('iiif-collection.index') }}" class="btn atom-btn-white w-100">
                <i class="fas fa-arrow-left me-2"></i>Back to Collections
            </a>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-plus-circle me-2"></i>Create Collection</h1>
@endsection

@section('content')
<div class="iiif-collection-form">
    <form method="POST" action="{{ route('iiif-collection.store') }}">
        @csrf

        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Collection Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="parent_id">Parent Collection <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">— None (Top Level) —</option>
                                @foreach($allCollections as $col)
                                <option value="{{ $col->id }}" {{ $parentId == $col->id ? 'selected' : '' }}>
                                    {{ e($col->display_name) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="description">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="attribution">Attribution <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" class="form-control" id="attribution" name="attribution" value="{{ old('attribution') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="viewing_hint">Viewing Hint <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="viewing_hint" name="viewing_hint">
                                <option value="individuals">Individuals</option>
                                <option value="paged">Paged</option>
                                <option value="continuous">Continuous</option>
                                <option value="multi-part">Multi-part</option>
                                <option value="top">Top</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" checked>
                        <label class="form-check-label" for="is_public">
                            Public
                         <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-save me-2"></i>Create Collection
            </button>
            <a href="{{ route('iiif-collection.index') }}" class="btn atom-btn-white">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </form>
</div>
@endsection
