@extends('theme::layouts.1col')
@section('title', ($entity ? 'Edit' : 'Create') . ' Rule')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-gavel me-2"></i>{{ $entity ? 'Edit' : 'Create' }} Rule</h1>
<form method="post" action="{{ $entity ? route('ric.entities.update-form', ['rules', $entity->slug]) : route('ric.entities.store-form', ['rules']) }}">
    @csrf
    @if($entity) @method('PUT') @endif
    @if(session('errors') && session('errors')->has('create'))
        <div class="alert alert-danger">{{ session('errors')->first('create') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ $entity->title ?? '' }}" required></div>
        <div class="col-md-4">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($typeChoices as $c)<option value="{{ $c->code }}" {{ ($entity->type_id ?? '') === $c->code ? 'selected' : '' }}>{{ $c->label }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-4"><label class="form-label">{{ __('Jurisdiction') }}</label><input type="text" name="jurisdiction" class="form-control" value="{{ $entity->jurisdiction ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Start Date') }}</label><input type="date" name="start_date" class="form-control" value="{{ $entity->start_date ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('End Date') }}</label><input type="date" name="end_date" class="form-control" value="{{ $entity->end_date ?? '' }}"></div>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Authority URI') }}</label>
        <input type="url" name="authority_uri" class="form-control" value="{{ $entity->authority_uri ?? '' }}" placeholder="{{ __('https://example.org/legislation/act-of-2024') }}">
        <div class="form-text">External IRI identifying the mandate or authority (emitted as <code>owl:sameAs</code> in RiC-O).</div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ $entity->description ?? '' }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Legislation') }}</label><textarea name="legislation" class="form-control" rows="3">{{ $entity->legislation ?? '' }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Sources') }}</label><textarea name="sources" class="form-control" rows="2">{{ $entity->sources ?? '' }}</textarea></div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        @if($entity)<a href="{{ route('ric.entities.show', ['rules', $entity->slug]) }}" class="btn btn-secondary">Cancel</a>@endif
    </div>
</form>
@endsection
