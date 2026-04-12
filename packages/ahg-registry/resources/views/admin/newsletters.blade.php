{{--
  Registry Admin — Newsletters
  Cloned from PSIS adminNewslettersSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Newsletters'))
@section('body-class', 'registry registry-admin-newsletters')

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
    <li class="breadcrumb-item active">{{ __('Newsletters') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Newsletters') }}</h1>
  <div class="d-flex gap-2">
    <span class="badge bg-secondary fs-6 align-self-center">{{ number_format($total) }} {{ __('total') }}</span>
    @if(Route::has('registry.admin.newsletterForm'))
      <a href="{{ route('registry.admin.newsletterForm') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('New Newsletter') }}</a>
    @endif
  </div>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.newsletters') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search newsletters...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($items->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Subject') }}</th>
        <th class="text-center">{{ __('Status') }}</th>
        <th>{{ __('Sent') }}</th>
        <th class="text-center">{{ __('Recipients') }}</th>
        <th>{{ __('Created') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td><strong>{{ $item->subject ?? '' }}</strong></td>
        <td class="text-center">
          @php
            $statusClass = match($item->status ?? '') {
              'sent' => 'bg-success',
              'scheduled' => 'bg-primary',
              'draft' => 'bg-secondary',
              'failed' => 'bg-danger',
              default => 'bg-secondary',
            };
          @endphp
          <span class="badge {{ $statusClass }}">{{ ucfirst($item->status ?? 'draft') }}</span>
        </td>
        <td><small class="text-muted">{{ !empty($item->sent_at) ? date('Y-m-d H:i', strtotime($item->sent_at)) : '-' }}</small></td>
        <td class="text-center"><span class="badge bg-light text-dark border">{{ (int) ($item->recipient_count ?? 0) }}</span></td>
        <td><small class="text-muted">{{ !empty($item->created_at) ? date('Y-m-d', strtotime($item->created_at)) : '-' }}</small></td>
        <td class="text-end">
          @if(Route::has('registry.admin.newsletterForm'))
            <a href="{{ route('registry.admin.newsletterForm', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
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
      <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.newsletters', ['page' => $page - 1, 'q' => $q]) }}">&laquo;</a></li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.admin.newsletters', ['page' => $i, 'q' => $q]) }}">{{ $i }}</a></li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.newsletters', ['page' => $page + 1, 'q' => $q]) }}">&raquo;</a></li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
  <h5>{{ __('No newsletters found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
