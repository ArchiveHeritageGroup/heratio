@extends('theme::layouts.1col')
@section('title', 'Create File Plan Node')
@section('body-class', 'admin records')

@section('title-block')
<h1>{{ __('Create File Plan Node') }}</h1>
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

<form method="post" action="{{ route('records.fileplan.store') }}">
    @csrf

    <div class="card mb-3">
        <div class="card-header">Node Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="parent_id" class="form-label">{{ __('Parent Node') }}</label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">-- Root (no parent) --</option>
                        @foreach($parentNodes as $pn)
                            <option value="{{ $pn->id }}" {{ (old('parent_id', $parentId) == $pn->id) ? 'selected' : '' }}>
                                {{ str_repeat('-- ', $pn->depth) }}{{ $pn->code }} - {{ $pn->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="node_type" class="form-label">Node Type <span class="text-danger">*</span></label>
                    <select name="node_type" id="node_type" class="form-select" required>
                        <option value="plan" {{ old('node_type') === 'plan' ? 'selected' : '' }}>{{ __('Plan') }}</option>
                        <option value="series" {{ old('node_type', 'series') === 'series' ? 'selected' : '' }}>{{ __('Series') }}</option>
                        <option value="sub_series" {{ old('node_type') === 'sub_series' ? 'selected' : '' }}>{{ __('Sub-series') }}</option>
                        <option value="file_group" {{ old('node_type') === 'file_group' ? 'selected' : '' }}>{{ __('File Group') }}</option>
                        <option value="volume" {{ old('node_type') === 'volume' ? 'selected' : '' }}>{{ __('Volume') }}</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="code" class="form-control" value="{{ old('code') }}" required maxlength="50" placeholder="{{ __('e.g. 1/2/3') }}">
                </div>

                <div class="col-md-8 mb-3">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">{{ __('Description') }}</label>
                <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">{{ __('Status') }}</label>
                <select name="status" id="status" class="form-select">
                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                    <option value="deprecated" {{ old('status') === 'deprecated' ? 'selected' : '' }}>{{ __('Deprecated') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Retention &amp; Disposal</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="disposal_class_id" class="form-label">{{ __('Disposal Class') }}</label>
                    <input type="number" name="disposal_class_id" id="disposal_class_id" class="form-control" value="{{ old('disposal_class_id') }}" placeholder="{{ __('Disposal class ID') }}">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="retention_period" class="form-label">{{ __('Retention Period') }}</label>
                    <input type="text" name="retention_period" id="retention_period" class="form-control" value="{{ old('retention_period') }}" maxlength="100" placeholder="{{ __('e.g. 5 years') }}">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="disposal_action" class="form-label">{{ __('Disposal Action') }}</label>
                    <select name="disposal_action" id="disposal_action" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="destroy" {{ old('disposal_action') === 'destroy' ? 'selected' : '' }}>{{ __('Destroy') }}</option>
                        <option value="transfer" {{ old('disposal_action') === 'transfer' ? 'selected' : '' }}>{{ __('Transfer') }}</option>
                        <option value="archive" {{ old('disposal_action') === 'archive' ? 'selected' : '' }}>{{ __('Archive') }}</option>
                        <option value="review" {{ old('disposal_action') === 'review' ? 'selected' : '' }}>{{ __('Review') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('records.fileplan.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ __('Create Node') }}</button>
    </div>
</form>
@endsection
