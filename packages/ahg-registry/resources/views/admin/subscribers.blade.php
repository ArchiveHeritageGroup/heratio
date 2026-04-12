{{--
  Registry Admin — Newsletter Subscribers
  Cloned from PSIS adminSubscribersSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Subscribers') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-subscribers')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 100;
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Subscribers') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-envelope-open-text me-2"></i>{{ __('Newsletter Subscribers') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($total) }} {{ __('total') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.subscribers') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search by email or name...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($items->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Email') }}</th>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Subscribed') }}</th>
        <th class="text-center">{{ __('Confirmed') }}</th>
        <th class="text-center">{{ __('Active') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td><strong>{{ $item->email ?? '' }}</strong></td>
        <td>{{ $item->name ?? '—' }}</td>
        <td><small class="text-muted">{{ !empty($item->created_at) ? date('Y-m-d', strtotime($item->created_at)) : '—' }}</small></td>
        <td class="text-center">
          @if(!empty($item->confirmed_at))
            <span class="badge bg-success"><i class="fas fa-check"></i></span>
          @else
            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i></span>
          @endif
        </td>
        <td class="text-center">
          @if(!isset($item->is_active) || $item->is_active)
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-secondary">{{ __('Unsubscribed') }}</span>
          @endif
        </td>
        <td class="text-end">
          @if(Route::has('registry.admin.subscribers.post'))
            <form method="post" action="{{ route('registry.admin.subscribers.post') }}" class="d-inline" onsubmit="return confirm('Delete this subscriber?');">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $item->id }}">
              <input type="hidden" name="form_action" value="delete">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
            </form>
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
      <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.subscribers', ['page' => $page - 1, 'q' => $q]) }}">&laquo;</a></li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.admin.subscribers', ['page' => $i, 'q' => $q]) }}">{{ $i }}</a></li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.subscribers', ['page' => $page + 1, 'q' => $q]) }}">&raquo;</a></li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-envelope-open-text fa-3x text-muted mb-3"></i>
  <h5>{{ __('No subscribers yet') }}</h5>
</div>
@endif
@endsection
