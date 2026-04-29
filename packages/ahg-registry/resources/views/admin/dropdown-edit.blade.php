{{--
  Registry Admin — Edit Dropdown Value
  Cloned from PSIS adminDropdownEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@php $isNew = empty($dropdown); @endphp

@section('title', $isNew ? __('Add Dropdown Value') : __('Edit Dropdown Value'))
@section('body-class', 'registry registry-admin-dropdown-edit')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dropdowns') }}">{{ __('Dropdown Manager') }}</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? __('Add') : __('Edit') }}</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <h1 class="h3 mb-4">{{ $isNew ? __('Add Dropdown Value') : __('Edit Dropdown Value') }}</h1>

    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="post" action="{{ $isNew ? route('registry.admin.dropdowns') : route('registry.admin.dropdownEdit', ['id' => (int) $dropdown->id]) }}">
      @csrf
      @if(!$isNew) @method('PUT') @endif
      <div class="card mb-4">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="dd-group" class="form-label">{{ __('Group') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="dd-group" name="dropdown_group" value="{{ old('dropdown_group', $dropdown->dropdown_group ?? '') }}" required list="group-list" placeholder="{{ __('e.g., institution_type') }}">
              <datalist id="group-list">
                @foreach(($existingGroups ?? []) as $g)
                  <option value="{{ $g }}">
                @endforeach
              </datalist>
              <small class="form-text text-muted">{{ __('Use snake_case. Select existing or type new.') }}</small>
            </div>
            <div class="col-md-6">
              <label for="dd-value" class="form-label">{{ __('Value') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="dd-value" name="value" value="{{ old('value', $dropdown->value ?? '') }}" required placeholder="{{ __('e.g., archive') }}">
              <small class="form-text text-muted">{{ __('Internal key stored in database.') }}</small>
            </div>
            <div class="col-md-6">
              <label for="dd-label" class="form-label">{{ __('Label') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="dd-label" name="label" value="{{ old('label', $dropdown->label ?? '') }}" required placeholder="{{ __('e.g., Archive') }}">
              <small class="form-text text-muted">{{ __('Display text shown to users.') }}</small>
            </div>
            <div class="col-md-3">
              <label for="dd-badge" class="form-label">{{ __('Badge Color') }}</label>
              @php $colors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark']; $selColor = old('badge_color', $dropdown->badge_color ?? ''); @endphp
              <select class="form-select" id="dd-badge" name="badge_color">
                <option value="">{{ __('-- None --') }}</option>
                @foreach($colors as $c)
                  <option value="{{ $c }}" {{ $selColor === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label for="dd-order" class="form-label">{{ __('Sort Order') }}</label>
              <input type="number" class="form-control" id="dd-order" name="sort_order" value="{{ old('sort_order', (int) ($dropdown->sort_order ?? 100)) }}" min="0">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="dd-active" name="is_active" value="1" {{ ($isNew || !empty($dropdown->is_active)) ? 'checked' : '' }}>
                <label class="form-check-label" for="dd-active">{{ __('Active') }}</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('registry.admin.dropdowns') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ $isNew ? __('Add Value') : __('Save Changes') }}</button>
      </div>
    </form>

  </div>
</div>
@endsection
