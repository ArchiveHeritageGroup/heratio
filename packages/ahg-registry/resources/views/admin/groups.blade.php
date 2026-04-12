{{--
  Registry Admin — Manage Groups
  Cloned from PSIS adminGroupsSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Manage Groups'))
@section('body-class', 'registry registry-admin-groups')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 50;
  $groupTypeBg = [
    'regional' => 'bg-primary',
    'topic' => 'bg-info text-dark',
    'software' => 'bg-success',
    'institutional' => 'bg-warning text-dark',
  ];
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Groups') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Manage Groups') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($total) }} {{ __('total') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.groups') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search groups...') }}">
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
        <th>{{ __('Type') }}</th>
        <th>{{ __('Country') }}</th>
        <th class="text-center">{{ __('Members') }}</th>
        <th class="text-center">{{ __('Verified') }}</th>
        <th class="text-center">{{ __('Featured') }}</th>
        <th class="text-center">{{ __('Active') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      @php $gtClass = $groupTypeBg[$item->group_type ?? ''] ?? 'bg-secondary'; @endphp
      <tr>
        <td>
          @if(Route::has('registry.groupView'))
            <a href="{{ route('registry.groupView', ['id' => $item->id]) }}" class="fw-semibold text-decoration-none">{{ $item->name ?? '' }}</a>
          @else
            <span class="fw-semibold">{{ $item->name ?? '' }}</span>
          @endif
        </td>
        <td><span class="badge {{ $gtClass }}">{{ ucfirst(str_replace('_', ' ', $item->group_type ?? '')) }}</span></td>
        <td>{{ $item->country ?? '' }}</td>
        <td class="text-center"><span class="badge bg-light text-dark border">{{ (int) ($item->member_count ?? 0) }}</span></td>
        <td class="text-center">
          @if(!empty($item->is_verified))
            <span class="badge bg-success"><i class="fas fa-check"></i></span>
          @else
            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i></span>
          @endif
        </td>
        <td class="text-center">
          @if(!empty($item->is_featured))
            <span class="badge bg-primary"><i class="fas fa-star"></i></span>
          @else
            <span class="text-muted">-</span>
          @endif
        </td>
        <td class="text-center">
          @if(!isset($item->is_active) || $item->is_active)
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-danger">{{ __('Suspended') }}</span>
          @endif
        </td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            @if(Route::has('registry.admin.groupEdit'))
              <a href="{{ route('registry.admin.groupEdit', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-edit"></i></a>
            @endif
            @if(Route::has('registry.admin.groupMembers'))
              <a href="{{ route('registry.admin.groupMembers', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-info" title="{{ __('Members') }}"><i class="fas fa-users"></i></a>
            @endif
          </div>
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
      <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.groups', ['page' => $page - 1, 'q' => $q]) }}">&laquo;</a></li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.admin.groups', ['page' => $i, 'q' => $q]) }}">{{ $i }}</a></li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.groups', ['page' => $page + 1, 'q' => $q]) }}">&raquo;</a></li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-users fa-3x text-muted mb-3"></i>
  <h5>{{ __('No groups found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
