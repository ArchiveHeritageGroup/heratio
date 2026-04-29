{{--
  Registry Admin — Edit Extension
  Cloned from PSIS adminExtensionEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Edit Extension') . ' — ' . ($erd->display_name ?? ''))
@section('body-class', 'registry registry-admin-extension-edit')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.erd') }}">{{ __('ERD') }}</a></li>
    <li class="breadcrumb-item active">{{ $erd->display_name ?? '' }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-puzzle-piece me-2"></i>{{ __('Edit Extension') }}: {{ $erd->display_name ?? '' }}</h1>

<form method="post" action="{{ route('registry.admin.extensionEdit', ['id' => $id]) }}">
  @csrf
  @method('PUT')

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Extension Metadata') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ __('Plugin name') }}</label>
          <input type="text" class="form-control" name="plugin_name" value="{{ old('plugin_name', $erd->plugin_name ?? '') }}" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Display name') }}</label>
          <input type="text" class="form-control" name="display_name" value="{{ old('display_name', $erd->display_name ?? '') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Category') }}</label>
          <input type="text" class="form-control" name="category" value="{{ old('category', $erd->category ?? 'general') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Icon') }}</label>
          <input type="text" class="form-control" name="icon" value="{{ old('icon', $erd->icon ?? 'fas fa-puzzle-piece') }}">
        </div>
        <div class="col-12">
          <label class="form-label">{{ __('Description') }}</label>
          <textarea class="form-control" name="description" rows="4">{{ old('description', $erd->description ?? '') }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label">{{ __('Tables JSON') }}</label>
          <textarea class="form-control font-monospace" name="tables_json" rows="8">{{ old('tables_json', is_string($erd->tables_json ?? null) ? $erd->tables_json : json_encode($erd->tables_json ?? [])) }}</textarea>
          <div class="form-text">{{ __('Array of table definitions.') }}</div>
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
