@extends('theme::layouts.1col')
@section('title', 'Edit File Plan Node: ' . $node->code)
@section('body-class', 'admin records')

@section('title-block')
<h1>Edit File Plan Node: {{ $node->code }}</h1>
@endsection

@section('content')

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ route('records.fileplan.update', $node->id) }}">
    @csrf
    @method('PUT')

    <div class="card mb-3">
        <div class="card-header">Node Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="parent_id" class="form-label">Parent Node</label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">-- Root (no parent) --</option>
                        @foreach($parentNodes as $pn)
                            @if($pn->id !== $node->id)
                                <option value="{{ $pn->id }}" {{ (old('parent_id', $node->parent_id) == $pn->id) ? 'selected' : '' }}>
                                    {{ str_repeat('-- ', $pn->depth) }}{{ $pn->code }} - {{ $pn->title }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="node_type" class="form-label">Node Type <span class="text-danger">*</span></label>
                    <select name="node_type" id="node_type" class="form-select" required>
                        <option value="plan" {{ old('node_type', $node->node_type) === 'plan' ? 'selected' : '' }}>Plan</option>
                        <option value="series" {{ old('node_type', $node->node_type) === 'series' ? 'selected' : '' }}>Series</option>
                        <option value="sub_series" {{ old('node_type', $node->node_type) === 'sub_series' ? 'selected' : '' }}>Sub-series</option>
                        <option value="file_group" {{ old('node_type', $node->node_type) === 'file_group' ? 'selected' : '' }}>File Group</option>
                        <option value="volume" {{ old('node_type', $node->node_type) === 'volume' ? 'selected' : '' }}>Volume</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="code" class="form-control" value="{{ old('code', $node->code) }}" required maxlength="50">
                </div>

                <div class="col-md-8 mb-3">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $node->title) }}" required maxlength="255">
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $node->description) }}</textarea>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="active" {{ old('status', $node->status) === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="closed" {{ old('status', $node->status) === 'closed' ? 'selected' : '' }}>Closed</option>
                    <option value="deprecated" {{ old('status', $node->status) === 'deprecated' ? 'selected' : '' }}>Deprecated</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Retention &amp; Disposal</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="disposal_class_id" class="form-label">Disposal Class</label>
                    <input type="number" name="disposal_class_id" id="disposal_class_id" class="form-control" value="{{ old('disposal_class_id', $node->disposal_class_id) }}" placeholder="Disposal class ID">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="retention_period" class="form-label">Retention Period</label>
                    <input type="text" name="retention_period" id="retention_period" class="form-control" value="{{ old('retention_period', $node->retention_period) }}" maxlength="100">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="disposal_action" class="form-label">Disposal Action</label>
                    <select name="disposal_action" id="disposal_action" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="destroy" {{ old('disposal_action', $node->disposal_action) === 'destroy' ? 'selected' : '' }}>Destroy</option>
                        <option value="transfer" {{ old('disposal_action', $node->disposal_action) === 'transfer' ? 'selected' : '' }}>Transfer</option>
                        <option value="archive" {{ old('disposal_action', $node->disposal_action) === 'archive' ? 'selected' : '' }}>Archive</option>
                        <option value="review" {{ old('disposal_action', $node->disposal_action) === 'review' ? 'selected' : '' }}>Review</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('records.fileplan.show', $node->id) }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Update Node</button>
    </div>
</form>
@endsection
