@extends('theme::layouts.1col')
@section('title', ($entity ? 'Edit' : 'Create') . ' Activity')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-running me-2"></i>{{ $entity ? 'Edit' : 'Create' }} Activity</h1>
<form method="post" action="{{ $entity ? route('ric.entities.update-form', ['activities', $entity->slug]) : route('ric.entities.store-form', ['activities']) }}">
    @csrf
    @if($entity) @method('PUT') @endif
    @if(session('errors') && session('errors')->has('create'))
        <div class="alert alert-danger">{{ session('errors')->first('create') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-8">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ $entity->name ?? '' }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($typeChoices as $c)
                <option value="{{ $c->code }}" {{ ($entity->type_id ?? '') === $c->code ? 'selected' : '' }}>{{ $c->label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-4"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="{{ $entity->start_date ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="{{ $entity->end_date ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">Date Display</label><input type="text" name="date_display" class="form-control" value="{{ $entity->date_display ?? '' }}"></div>
    </div>
    <div class="mb-3">
        <label class="form-label">Place</label>
        <select name="place_id" class="form-select">
            <option value="">-- No place --</option>
            @foreach(($placeChoices ?? []) as $p)
                <option value="{{ $p->id }}" {{ (string)($entity->place_id ?? '') === (string)$p->id ? 'selected' : '' }}>{{ $p->name ?: '(unnamed)' }}</option>
            @endforeach
        </select>
        <div class="form-text">Where the activity took place. Creates a <code>rico:tookPlaceAt</code> link in the graph.</div>
    </div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4">{{ $entity->description ?? '' }}</textarea></div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        @if($entity)<a href="{{ route('ric.entities.show', ['activities', $entity->slug]) }}" class="btn btn-secondary">Cancel</a>@endif
    </div>
</form>
@endsection
