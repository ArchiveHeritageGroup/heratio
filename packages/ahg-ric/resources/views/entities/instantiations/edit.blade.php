@extends('theme::layouts.1col')
@section('title', ($entity ? 'Edit' : 'Create') . ' Instantiation')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-file-alt me-2"></i>{{ $entity ? 'Edit' : 'Create' }} Instantiation</h1>
<form method="post" action="{{ $entity ? route('ric.entities.update-form', ['instantiations', $entity->slug]) : route('ric.entities.store-form', ['instantiations']) }}">
    @csrf
    @if($entity) @method('PUT') @endif
    @if(session('errors') && session('errors')->has('create'))
        <div class="alert alert-danger">{{ session('errors')->first('create') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ $entity->title ?? '' }}" required></div>
        <div class="col-md-4">
            <label class="form-label">{{ __('Carrier Type') }}</label>
            <select name="carrier_type" class="form-select">
                <option value="">-- Select --</option>
                @foreach($typeChoices as $c)<option value="{{ $c->code }}" {{ ($entity->carrier_type ?? '') === $c->code ? 'selected' : '' }}>{{ $c->label }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-4"><label class="form-label">{{ __('MIME Type') }}</label><input type="text" name="mime_type" class="form-control" value="{{ $entity->mime_type ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Extent') }}</label><input type="number" step="any" name="extent_value" class="form-control" value="{{ $entity->extent_value ?? '' }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('Unit') }}</label><input type="text" name="extent_unit" class="form-control" value="{{ $entity->extent_unit ?? '' }}"></div>
    </div>
    @include('ahg-ric::_fk-autocomplete', [
        'name' => 'record_id',
        'types' => 'io',
        'label' => 'Parent Record',
        'currentId' => $entity->record_id ?? null,
        'currentLabel' => $currentRecordLabel ?? null,
        'hint' => 'The archival description this instantiation manifests. Required for most instantiations.',
    ])
    @include('ahg-ric::_fk-autocomplete', [
        'name' => 'digital_object_id',
        'types' => 'digital_object',
        'label' => 'Digital Object',
        'currentId' => $entity->digital_object_id ?? null,
        'currentLabel' => $currentDigitalObjectLabel ?? null,
        'hint' => 'Optional link to the bitstream in the digital object store (image, PDF, TIFF, etc.).',
    ])
    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ $entity->description ?? '' }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Technical Characteristics') }}</label><textarea name="technical_characteristics" class="form-control" rows="3">{{ $entity->technical_characteristics ?? '' }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Production Technical Characteristics') }}</label><textarea name="production_technical_characteristics" class="form-control" rows="3">{{ $entity->production_technical_characteristics ?? '' }}</textarea><div class="form-text">Equipment/process used to produce this instantiation (scanner, camera, OCR tool, etc.).</div></div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
        @if($entity)<a href="{{ route('ric.entities.show', ['instantiations', $entity->slug]) }}" class="btn btn-secondary">Cancel</a>@endif
    </div>
</form>
@endsection
