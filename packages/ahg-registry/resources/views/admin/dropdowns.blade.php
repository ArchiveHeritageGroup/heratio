{{--
  Registry Admin — Dropdown Manager
  Cloned from PSIS adminDropdownsSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Dropdown Manager'))
@section('body-class', 'registry registry-admin-dropdowns')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $grouped = $items->groupBy('dropdown_group');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Dropdowns') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Dropdown Manager') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($grouped->count()) }} {{ __('taxonomies') }} / {{ number_format($total) }} {{ __('values') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.dropdowns') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search dropdowns...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($grouped->isNotEmpty())
<div class="accordion" id="dropdownAccordion">
  @foreach($grouped as $taxonomy => $values)
    @php $tid = 'tax-' . md5($taxonomy); @endphp
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $tid }}">
          <strong>{{ $taxonomy }}</strong>
          <span class="badge bg-secondary ms-2">{{ $values->count() }}</span>
        </button>
      </h2>
      <div id="{{ $tid }}" class="accordion-collapse collapse" data-bs-parent="#dropdownAccordion">
        <div class="accordion-body">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Label') }}</th>
                <th class="text-center">{{ __('Sort') }}</th>
                <th class="text-center">{{ __('Active') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($values as $v)
                <tr>
                  <td><code>{{ $v->value ?? '' }}</code></td>
                  <td>{{ $v->label ?? '' }}</td>
                  <td class="text-center">{{ $v->sort_order ?? 0 }}</td>
                  <td class="text-center">
                    @if(!isset($v->is_active) || $v->is_active)
                      <span class="badge bg-success">{{ __('Active') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                    @endif
                  </td>
                  <td class="text-end">
                    @if(Route::has('registry.admin.dropdownEdit'))
                      <a href="{{ route('registry.admin.dropdownEdit', ['id' => (int) $v->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endforeach
</div>
@else
<div class="text-center py-5">
  <i class="fas fa-list fa-3x text-muted mb-3"></i>
  <h5>{{ __('No dropdowns found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
