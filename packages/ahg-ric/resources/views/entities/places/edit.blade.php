@extends('theme::layouts.1col')
@section('title', ($entity ? 'Edit' : 'Create') . ' Place')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>{{ $entity ? 'Edit' : 'Create' }} Place</h1>
<form method="post" action="{{ $entity ? route('ric.entities.update-form', ['places', $entity->slug]) : route('ric.entities.store-form', ['places']) }}">
    @csrf
    @if($entity) @method('PUT') @endif
    @if(session('errors') && session('errors')->has('create'))
        <div class="alert alert-danger">{{ session('errors')->first('create') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $entity->name ?? '' }}" required></div>
        <div class="col-md-4">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($typeChoices as $c)<option value="{{ $c->code }}" {{ ($entity->type_id ?? '') === $c->code ? 'selected' : '' }}>{{ $c->label }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-4"><label class="form-label">{{ __('Latitude') }}</label><input type="number" step="any" name="latitude" class="form-control" value="{{ $entity->latitude ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Longitude') }}</label><input type="number" step="any" name="longitude" class="form-control" value="{{ $entity->longitude ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Authority URI') }}</label><input type="url" name="authority_uri" class="form-control" value="{{ $entity->authority_uri ?? '' }}"></div>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Parent Place') }}</label>
        <select name="parent_id" class="form-select">
            <option value="">-- None (top-level) --</option>
            @foreach(($parentChoices ?? []) as $p)
                <option value="{{ $p->id }}" {{ (string)($entity->parent_id ?? '') === (string)$p->id ? 'selected' : '' }}>{{ $p->name ?: '(unnamed)' }}</option>
            @endforeach
        </select>
        <div class="form-text">For building hierarchies — e.g. city inside province inside country.</div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Address') }}</label><textarea name="address" class="form-control" rows="2">{{ $entity->address ?? '' }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ $entity->description ?? '' }}</textarea></div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
        @if($entity)<a href="{{ route('ric.entities.show', ['places', $entity->slug]) }}" class="btn btn-secondary">Cancel</a>@endif
    </div>
</form>
@endsection
