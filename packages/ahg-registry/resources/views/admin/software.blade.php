{{--
  Registry Admin — Manage Software
  Cloned from PSIS ahgRegistryPlugin/modules/registry/templates/adminSoftwareSuccess.php.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Manage Software'))
@section('body-class', 'registry registry-admin-software')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 50;
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Software') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Manage Software') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($total) }} {{ __('total') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.software') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search software...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($items->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Vendor') }}</th>
        <th>{{ __('Category') }}</th>
        <th>{{ __('License') }}</th>
        <th class="text-center">{{ __('Active') }}</th>
        <th>{{ __('Created') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td>
          @if(Route::has('registry.softwareView'))
            <a href="{{ route('registry.softwareView', ['id' => $item->id]) }}" class="fw-semibold text-decoration-none">{{ $item->name ?? '' }}</a>
          @else
            <span class="fw-semibold">{{ $item->name ?? '' }}</span>
          @endif
        </td>
        <td>{{ $item->vendor_name ?? '-' }}</td>
        <td><span class="badge bg-secondary">{{ $item->category ?? '' }}</span></td>
        <td>{{ $item->license ?? '' }}</td>
        <td class="text-center">
          @if(!isset($item->is_active) || $item->is_active)
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-danger">{{ __('Inactive') }}</span>
          @endif
        </td>
        <td><small class="text-muted">{{ !empty($item->created_at) ? date('Y-m-d', strtotime($item->created_at)) : '-' }}</small></td>
        <td class="text-end">
          @if(Route::has('registry.softwareView'))
            <a href="{{ route('registry.softwareView', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@if($total > $limit)
  @php $totalPages = (int) ceil($total / $limit); @endphp
  <nav aria-label="{{ __('Page navigation') }}" class="mt-3">
    <ul class="pagination justify-content-center">
      <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}">
        <a class="page-link" href="{{ route('registry.admin.software', ['page' => $page - 1, 'q' => $q]) }}">&laquo;</a>
      </li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}">
          <a class="page-link" href="{{ route('registry.admin.software', ['page' => $i, 'q' => $q]) }}">{{ $i }}</a>
        </li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}">
        <a class="page-link" href="{{ route('registry.admin.software', ['page' => $page + 1, 'q' => $q]) }}">&raquo;</a>
      </li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-code fa-3x text-muted mb-3"></i>
  <h5>{{ __('No software found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
