{{--
  Registry Admin — Manage Standards
  Cloned from PSIS ahgRegistryPlugin/modules/registry/templates/adminStandardsSuccess.php.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Manage Standards'))
@section('body-class', 'registry registry-admin-standards')

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
    <li class="breadcrumb-item active">{{ __('Standards') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Manage Standards') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($total) }} {{ __('total') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.standards') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search standards...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($items->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Code') }}</th>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Category') }}</th>
        <th>{{ __('Organization') }}</th>
        <th class="text-center">{{ __('Active') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td><code>{{ $item->code ?? '' }}</code></td>
        <td>
          @if(Route::has('registry.standardView'))
            <a href="{{ route('registry.standardView', ['id' => $item->id]) }}" class="fw-semibold text-decoration-none">{{ $item->name ?? '' }}</a>
          @else
            <span class="fw-semibold">{{ $item->name ?? '' }}</span>
          @endif
        </td>
        <td><span class="badge bg-secondary">{{ $item->category ?? '' }}</span></td>
        <td>{{ $item->organization ?? '' }}</td>
        <td class="text-center">
          @if(!isset($item->is_active) || $item->is_active)
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-danger">{{ __('Inactive') }}</span>
          @endif
        </td>
        <td class="text-end">
          @if(Route::has('registry.admin.standardEdit'))
            <a href="{{ route('registry.admin.standardEdit', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
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
        <a class="page-link" href="{{ route('registry.admin.standards', ['page' => $page - 1, 'q' => $q]) }}">&laquo;</a>
      </li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}">
          <a class="page-link" href="{{ route('registry.admin.standards', ['page' => $i, 'q' => $q]) }}">{{ $i }}</a>
        </li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}">
        <a class="page-link" href="{{ route('registry.admin.standards', ['page' => $page + 1, 'q' => $q]) }}">&raquo;</a>
      </li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
  <h5>{{ __('No standards found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
