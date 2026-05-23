{{-- heratio#144 — Strongroom create/edit (single form, $room null = create) --}}
@extends('theme::layouts.1col')

@php $isNew = ($room === null); @endphp

@section('title', $isNew ? __('Add strongroom') : __('Edit :name', ['name' => $room->name]))
@section('body-class', 'edit strongroom')

@section('content')
  <h1>{{ $isNew ? __('Add strongroom') : __('Edit :name', ['name' => $room->name]) }}</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="post"
        action="{{ $isNew ? route('strongroom.store') : route('strongroom.update', ['slug' => $room->slug]) }}"
        class="mt-3" style="max-width: 48rem;">
    @csrf

    <div class="mb-3">
      <label for="sr_name" class="form-label fw-semibold">{{ __('Name') }} <span class="text-danger">*</span></label>
      <input type="text" id="sr_name" name="name" class="form-control" required maxlength="255"
             value="{{ old('name', $room->name ?? '') }}">
      <div class="form-text">{{ __('Required. e.g. "Room A1" or "North annex shelving".') }}</div>
    </div>

    <div class="mb-3">
      <label for="sr_location" class="form-label fw-semibold">{{ __('Location') }}</label>
      <textarea id="sr_location" name="location_description" class="form-control" rows="2">{{ old('location_description', $room->location_description ?? '') }}</textarea>
      <div class="form-text">{{ __('Where in the building / on the site this room is. Free text.') }}</div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="sr_capacity_value" class="form-label fw-semibold">{{ __('Capacity') }}</label>
        <input type="number" id="sr_capacity_value" name="capacity_value" class="form-control"
               min="0" step="0.01" value="{{ old('capacity_value', $room->capacity_value ?? '') }}">
        <div class="form-text">{{ __('Leave blank if not tracking capacity.') }}</div>
      </div>
      <div class="col-md-6">
        <label for="sr_capacity_unit" class="form-label fw-semibold">{{ __('Unit') }}</label>
        <select id="sr_capacity_unit" name="capacity_unit" class="form-select">
          @php $currentUnit = old('capacity_unit', $room->capacity_unit ?? 'linear_meters'); @endphp
          @foreach($capacityUnits as $key => $label)
            <option value="{{ $key }}" @selected($key === $currentUnit)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="mb-4">
      <label for="sr_notes" class="form-label fw-semibold">{{ __('Notes') }}</label>
      <textarea id="sr_notes" name="notes" class="form-control" rows="4">{{ old('notes', $room->notes ?? '') }}</textarea>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ $isNew ? __('Create strongroom') : __('Save changes') }}
      </button>
      <a href="{{ $isNew ? route('strongroom.browse') : route('strongroom.show', ['slug' => $room->slug]) }}"
         class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>
@endsection
