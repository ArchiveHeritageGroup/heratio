{{-- heratio#146 — Exhibition space create/edit --}}
@extends('theme::layouts.1col')

@php $isNew = ($space === null); @endphp

@section('title', $isNew ? __('Add exhibition space') : __('Edit :name', ['name' => $space->name]))
@section('body-class', 'edit exhibition-space')

@section('content')
  <h1><i class="fas fa-palette me-2"></i>{{ $isNew ? __('Add exhibition space') : __('Edit :name', ['name' => $space->name]) }}</h1>

  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
  @endif

  <form method="POST"
        action="{{ $isNew ? route('exhibition-space.store') : route('exhibition-space.update', ['slug' => $space->slug]) }}"
        class="mt-3" style="max-width: 56rem;">
    @csrf

    <div class="row g-3">
      <div class="col-md-8">
        <label for="es_name" class="form-label fw-semibold">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" id="es_name" name="name" class="form-control" required maxlength="255"
               value="{{ old('name', $space->name ?? '') }}">
      </div>
      <div class="col-md-4">
        <label for="es_space_type" class="form-label fw-semibold">{{ __('Type') }}</label>
        <select id="es_space_type" name="space_type" class="form-select">
          @php $currentType = old('space_type', $space->space_type ?? 'gallery'); @endphp
          @foreach($spaceTypes as $key => $label)
            <option value="{{ $key }}" @selected($key === $currentType)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-6">
        <label for="es_building" class="form-label fw-semibold">{{ __('Building') }}</label>
        <input type="text" id="es_building" name="building" class="form-control" value="{{ old('building', $space->building ?? '') }}">
      </div>
      <div class="col-md-6">
        <label for="es_floor" class="form-label fw-semibold">{{ __('Floor') }}</label>
        <input type="text" id="es_floor" name="floor" class="form-control" value="{{ old('floor', $space->floor ?? '') }}">
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-4">
        <label for="es_capacity_value" class="form-label fw-semibold">{{ __('Capacity') }}</label>
        <input type="number" id="es_capacity_value" name="capacity_value" class="form-control"
               min="0" step="0.01" value="{{ old('capacity_value', $space->capacity_value ?? '') }}">
        <small class="text-muted">{{ __('Leave blank if not tracking capacity.') }}</small>
      </div>
      <div class="col-md-4">
        <label for="es_capacity_unit" class="form-label fw-semibold">{{ __('Unit') }}</label>
        <select id="es_capacity_unit" name="capacity_unit" class="form-select">
          @php $currentUnit = old('capacity_unit', $space->capacity_unit ?? 'linear_wall_meters'); @endphp
          @foreach($capacityUnits as $key => $label)
            <option value="{{ $key }}" @selected($key === $currentUnit)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label for="es_lux" class="form-label fw-semibold">{{ __('Lighting target (lux)') }}</label>
        <input type="number" id="es_lux" name="lighting_lux_target" class="form-control"
               min="0" step="0.01" value="{{ old('lighting_lux_target', $space->lighting_lux_target ?? '') }}">
      </div>
    </div>

    <hr class="my-3">
    <h6 class="fw-semibold">{{ __('3D room (digital twin)') }}</h6>
    <div class="row g-3">
      <div class="col-md-2">
        <label for="es_room_w" class="form-label fw-semibold">{{ __('Room width (m)') }}</label>
        <input type="number" id="es_room_w" name="room_w" class="form-control" min="1" max="200" step="any" placeholder="18" value="{{ old('room_w', $space->room_w ?? '') }}">
      </div>
      <div class="col-md-2">
        <label for="es_room_d" class="form-label fw-semibold">{{ __('Room depth (m)') }}</label>
        <input type="number" id="es_room_d" name="room_d" class="form-control" min="1" max="200" step="any" placeholder="14" value="{{ old('room_d', $space->room_d ?? '') }}">
      </div>
      <div class="col-md-2">
        <label for="es_room_h" class="form-label fw-semibold">{{ __('Room height (m)') }}</label>
        <input type="number" id="es_room_h" name="room_h" class="form-control" min="1" max="30" step="any" placeholder="4" value="{{ old('room_h', $space->room_h ?? '') }}">
      </div>
      <div class="col-md-4">
        <label for="es_building_id" class="form-label fw-semibold">{{ __('Building ID') }}</label>
        <input type="text" id="es_building_id" name="building_id" class="form-control" maxlength="64" value="{{ old('building_id', $space->building_id ?? '') }}">
        <small class="text-muted">{{ __('Spaces sharing a Building ID become connected rooms you can walk between.') }}</small>
      </div>
      <div class="col-md-2">
        <label for="es_building_seq" class="form-label fw-semibold">{{ __('Room order') }}</label>
        <input type="number" id="es_building_seq" name="building_seq" class="form-control" min="0" step="1" value="{{ old('building_seq', $space->building_seq ?? 0) }}">
      </div>
    </div>

    <div class="mb-3 mt-3">
      <label for="es_notes" class="form-label fw-semibold">{{ __('Notes') }}</label>
      <textarea id="es_notes" name="notes" class="form-control" rows="4">{{ old('notes', $space->notes ?? '') }}</textarea>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ $isNew ? __('Create exhibition space') : __('Save changes') }}
      </button>
      <a href="{{ $isNew ? route('exhibition-space.browse') : route('exhibition-space.show', ['slug' => $space->slug]) }}"
         class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>
@endsection
