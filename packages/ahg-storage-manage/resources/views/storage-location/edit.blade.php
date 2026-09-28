{{-- heratio#1514 - storage location create / edit (port of AtoM storageLocation/create + edit) --}}
@extends('theme::layouts.1col')

@php $editing = $location !== null; @endphp

@section('title', $editing ? __('Edit :name', ['name' => $location->name]) : __('Add storage location'))
@section('body-class', 'edit storagelocation')

@section('content')
  <h1 class="mb-2">{{ $editing ? __('Edit storage location') : __('Add storage location') }}</h1>

  @if($editing || $parent)
    <div class="mb-3">
      <span class="text-muted small">{{ $editing ? __('Editing in') : __('Adding under') }}:</span>
      @include('ahg-storage-manage::storage-location._path', ['path' => $path, 'linkLast' => ! $editing])
    </div>
  @endif

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

  @php
    $val = fn (string $field) => old($field, $editing ? $location->{$field} : null);
    $parentValue = old('parent_id', $editing ? $location->parent_id : ($parent->id ?? null));
  @endphp

  <form method="post"
        action="{{ $editing ? route('storagelocation.update', ['slug' => $location->slug]) : route('storagelocation.store') }}"
        style="max-width: 48rem;">
    @csrf

    <div class="mb-3">
      <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
      <input type="text" id="name" name="name" class="form-control" maxlength="255" required value="{{ $val('name') }}">
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="location_type" class="form-label">{{ __('Location type') }} <span class="text-danger">*</span></label>
        <select id="location_type" name="location_type" class="form-select" required>
          <option value="">{{ __('Select a type') }}</option>
          @foreach($types as $code => $label)
            <option value="{{ $code }}" @selected((string) $val('location_type') === (string) $code)>{{ $label }}</option>
          @endforeach
        </select>
        <div class="form-text">{{ __('Managed in the Dropdown Manager (storage_location_type).') }}</div>
      </div>
      <div class="col-md-6">
        <label for="parent_id" class="form-label">{{ __('Parent location') }}</label>
        <select id="parent_id" name="parent_id" class="form-select">
          <option value="">{{ __('None (root level)') }}</option>
          @foreach($parents as $candidate)
            <option value="{{ $candidate->id }}" @selected((string) $parentValue === (string) $candidate->id)>
              {{ str_repeat('- ', (int) $candidate->level) }}{{ $candidate->name }}
            </option>
          @endforeach
        </select>
        @if($editing)
          <div class="form-text">{{ __('Moving a location moves everything under it.') }}</div>
        @endif
      </div>
    </div>

    <div class="mb-3">
      <label for="description" class="form-label">{{ __('Description') }}</label>
      <textarea id="description" name="description" class="form-control" rows="3">{{ $val('description') }}</textarea>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="capacity_value" class="form-label">{{ __('Capacity') }}</label>
        <input type="number" step="0.01" min="0" id="capacity_value" name="capacity_value" class="form-control" value="{{ $val('capacity_value') }}">
      </div>
      <div class="col-md-6">
        <label for="capacity_unit" class="form-label">{{ __('Capacity unit') }}</label>
        <select id="capacity_unit" name="capacity_unit" class="form-select">
          <option value="">{{ __('None') }}</option>
          @foreach($units as $code => $label)
            <option value="{{ $code }}" @selected((string) $val('capacity_unit') === (string) $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="notes" class="form-label">{{ __('Notes') }}</label>
      <textarea id="notes" name="notes" class="form-control" rows="3">{{ $val('notes') }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary">{{ $editing ? __('Save changes') : __('Add location') }}</button>
    <a href="{{ $editing ? route('storagelocation.show', ['slug' => $location->slug]) : route('storagelocation.browse') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  </form>
@endsection
