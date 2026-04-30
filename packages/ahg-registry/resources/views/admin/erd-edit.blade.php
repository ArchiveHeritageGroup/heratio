{{--
  Registry Admin — Edit ERD
  Cloned from PSIS adminErdEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Edit ERD') . ' — ' . ($erd->display_name ?? ''))
@section('body-class', 'registry registry-admin-erd-edit')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.erd') }}">{{ __('ERD') }}</a></li>
    <li class="breadcrumb-item active">{{ $erd->display_name ?? '' }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ __('Edit ERD') }}: {{ $erd->display_name ?? '' }}</h1>

<form method="post" action="{{ route('registry.admin.erdEdit', ['id' => $id]) }}">
  @csrf
  @method('PUT')

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Basic Information') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ __('Plugin name') }}</label>
          <input type="text" class="form-control" name="plugin_name" value="{{ old('plugin_name', $erd->plugin_name ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Display name') }}</label>
          <input type="text" class="form-control" name="display_name" value="{{ old('display_name', $erd->display_name ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Slug') }}</label>
          <input type="text" class="form-control" name="slug" value="{{ old('slug', $erd->slug ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Category') }}</label>
          <input type="text" class="form-control" name="category" value="{{ old('category', $erd->category ?? 'general') }}">
        </div>
        <div class="col-12">
          <label class="form-label">{{ __('Description') }}</label>
          <textarea class="form-control" name="description" rows="3">{{ old('description', $erd->description ?? '') }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Diagram') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Diagram (Mermaid or ASCII)') }}</label>
        <textarea class="form-control font-monospace" name="diagram" rows="10">{{ old('diagram', $erd->diagram ?? '') }}</textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Diagram image URL') }}</label>
        <input type="text" class="form-control" name="diagram_image" value="{{ old('diagram_image', $erd->diagram_image ?? '') }}">
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Notes') }}</label>
        <textarea class="form-control" name="notes" rows="3">{{ old('notes', $erd->notes ?? '') }}</textarea>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Display') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Icon') }}</label>
          <input type="text" class="form-control" name="icon" value="{{ old('icon', $erd->icon ?? 'fas fa-database') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Color') }}</label>
          <input type="text" class="form-control" name="color" value="{{ old('color', $erd->color ?? 'primary') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Sort order') }}</label>
          <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $erd->sort_order ?? 100) }}">
        </div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ ($erd->is_active ?? 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
    <a href="{{ route('registry.admin.erd') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  </div>
</form>
@endsection
