{{--
  Registry Admin — Edit Standard
  Cloned from PSIS adminStandardEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@php $isNew = empty($standard); @endphp

@section('title', $isNew ? __('Add Standard') : __('Edit Standard'))
@section('body-class', 'registry registry-admin-standard-edit')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.standards') }}">{{ __('Standards') }}</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? __('Add') : __('Edit') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ $isNew ? __('Add Standard') : __('Edit Standard') }}</h1>

@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
  </div>
@endif

<form method="post" action="{{ $isNew ? route('registry.admin.standards') : route('registry.admin.standardEdit', ['id' => (int) $standard->id]) }}">
  @csrf
  @if(!$isNew)
    @method('PUT')
    <input type="hidden" name="id" value="{{ (int) $standard->id }}">
  @endif
  <input type="hidden" name="form_action" value="save">

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title mb-0">{{ __('Standard Details') }}</h5></div>
    <div class="card-body">

      <div class="row mb-3">
        <div class="col-md-8">
          <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="name" name="name" required value="{{ old('name', $standard->name ?? '') }}">
        </div>
        <div class="col-md-4">
          <label for="acronym" class="form-label">{{ __('Acronym') }}</label>
          <input type="text" class="form-control" id="acronym" name="acronym" value="{{ old('acronym', $standard->acronym ?? '') }}">
        </div>
      </div>

      <div class="mb-3">
        <label for="category" class="form-label">{{ __('Category') }}</label>
        @php $categoryOptions = ['descriptive', 'preservation', 'rights', 'accounting', 'compliance', 'metadata', 'interchange', 'sector']; $selCat = old('category', $standard->category ?? ''); @endphp
        <select class="form-select" id="category" name="category">
          <option value="">{{ __('-- Select category --') }}</option>
          @foreach($categoryOptions as $opt)
            <option value="{{ $opt }}" {{ $selCat === $opt ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $opt)) }}</option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="short_description" class="form-label">{{ __('Short Description') }}</label>
        <textarea class="form-control" id="short_description" name="short_description" rows="2">{{ old('short_description', $standard->short_description ?? '') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label">{{ __('Description') }}</label>
        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $standard->description ?? '') }}</textarea>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="website_url" class="form-label">{{ __('Website URL') }}</label>
          <input type="url" class="form-control" id="website_url" name="website_url" value="{{ old('website_url', $standard->website_url ?? '') }}">
        </div>
        <div class="col-md-6">
          <label for="issuing_body" class="form-label">{{ __('Issuing Body') }}</label>
          <input type="text" class="form-control" id="issuing_body" name="issuing_body" value="{{ old('issuing_body', $standard->issuing_body ?? '') }}">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="current_version" class="form-label">{{ __('Current Version') }}</label>
          <input type="text" class="form-control" id="current_version" name="current_version" value="{{ old('current_version', $standard->current_version ?? '') }}">
        </div>
        <div class="col-md-6">
          <label for="publication_year" class="form-label">{{ __('Publication Year') }}</label>
          <input type="number" class="form-control" id="publication_year" name="publication_year" min="1900" max="2100" value="{{ old('publication_year', $standard->publication_year ?? '') }}">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Sector Applicability') }}</label>
        @php
          $sectorOptions = ['archive', 'library', 'museum', 'gallery', 'dam'];
          $rawApplicability = $standard->sector_applicability ?? null;
          $currentSectors = [];
          if (is_string($rawApplicability)) {
            $decoded = json_decode($rawApplicability, true);
            if (is_array($decoded)) { $currentSectors = $decoded; }
          } elseif (is_array($rawApplicability)) {
            $currentSectors = $rawApplicability;
          }
        @endphp
        <div class="d-flex flex-wrap gap-3">
          @foreach($sectorOptions as $sector)
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="sector_applicability[]" value="{{ $sector }}" id="sector_{{ $sector }}" {{ in_array($sector, $currentSectors) ? 'checked' : '' }}>
            <label class="form-check-label" for="sector_{{ $sector }}">{{ ucfirst($sector) }}</label>
          </div>
          @endforeach
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured" {{ !empty($standard->is_featured) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_featured">{{ __('Featured') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ ($isNew || !isset($standard->is_active) || $standard->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <label for="sort_order" class="form-label">{{ __('Sort Order') }}</label>
          <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', (int) ($standard->sort_order ?? 0)) }}">
        </div>
      </div>

    </div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button>
    <a href="{{ route('registry.admin.standards') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  </div>
</form>

@if(!$isNew && !empty($extensions))
<div class="card mb-4" id="extensions">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">{{ __('Heratio Extensions') }}</h5>
    {{-- Add-extension flow requires a create route that the router doesn't expose yet; link omitted until wired. --}}
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Plugin') }}</th>
            <th style="width: 60px;">{{ __('Sort') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($extensions as $ext)
          <tr>
            <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $ext->extension_type ?? '')) }}</span></td>
            <td>{{ $ext->title ?? '' }}</td>
            <td>@if(!empty($ext->plugin_name))<code>{{ $ext->plugin_name }}</code>@else<span class="text-muted">-</span>@endif</td>
            <td>{{ (int) ($ext->sort_order ?? 0) }}</td>
            <td class="text-end">
              @if(Route::has('registry.admin.extensionEdit'))
                <a href="{{ route('registry.admin.extensionEdit', ['id' => (int) $ext->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}">
                  <i class="fas fa-edit"></i>
                </a>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
@endsection
