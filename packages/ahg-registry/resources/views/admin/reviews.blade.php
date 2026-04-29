{{--
  Registry Admin — Moderate Reviews
  Cloned from PSIS adminReviewsSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Moderate Reviews'))
@section('body-class', 'registry registry-admin-reviews')

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
    <li class="breadcrumb-item active">{{ __('Reviews') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Moderate Reviews') }}</h1>
  <span class="badge bg-secondary fs-6">{{ number_format($total) }} {{ __('total') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.reviews') }}" class="row g-2">
    <div class="col">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search reviews...') }}">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">{{ __('All statuses') }}</option>
        <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
        <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
        <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
        <option value="flagged" {{ ($status ?? '') === 'flagged' ? 'selected' : '' }}>{{ __('Flagged') }}</option>
      </select>
    </div>
    <div class="col-md-auto">
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
        <th>{{ __('Rating') }}</th>
        <th>{{ __('Reviewer') }}</th>
        <th class="text-center">{{ __('Status') }}</th>
        <th>{{ __('Created') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $item)
      <tr>
        <td><strong>{{ $item->title ?? '—' }}</strong></td>
        <td>
          @php $rating = (int) ($item->rating ?? 0); @endphp
          @for($i = 1; $i <= 5; $i++)
            <i class="fa{{ $i <= $rating ? 's' : 'r' }} fa-star text-warning small"></i>
          @endfor
        </td>
        <td>{{ $item->reviewer_name ?? '—' }}</td>
        <td class="text-center">
          @php
            $statusClass = match($item->status ?? '') {
              'approved' => 'bg-success',
              'pending' => 'bg-warning text-dark',
              'rejected' => 'bg-danger',
              'flagged' => 'bg-dark',
              default => 'bg-secondary',
            };
          @endphp
          <span class="badge {{ $statusClass }}">{{ ucfirst($item->status ?? 'pending') }}</span>
        </td>
        <td><small class="text-muted">{{ !empty($item->created_at) ? date('Y-m-d', strtotime($item->created_at)) : '-' }}</small></td>
        <td class="text-end">
          @if(Route::has('registry.admin.reviews.post'))
            <form method="post" action="{{ route('registry.admin.reviews.post') }}" class="d-inline">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $item->id }}">
              <input type="hidden" name="form_action" value="approve">
              <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i></button>
            </form>
            <form method="post" action="{{ route('registry.admin.reviews.post') }}" class="d-inline">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $item->id }}">
              <input type="hidden" name="form_action" value="reject">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
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
      <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.reviews', ['page' => $page - 1, 'q' => $q, 'status' => $status]) }}">&laquo;</a></li>
      @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
        <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.admin.reviews', ['page' => $i, 'q' => $q, 'status' => $status]) }}">{{ $i }}</a></li>
      @endfor
      <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.admin.reviews', ['page' => $page + 1, 'q' => $q, 'status' => $status]) }}">&raquo;</a></li>
    </ul>
  </nav>
@endif

@else
<div class="text-center py-5">
  <i class="fas fa-star fa-3x text-muted mb-3"></i>
  <h5>{{ __('No reviews found') }}</h5>
  <p class="text-muted">{{ __('Try adjusting your search terms.') }}</p>
</div>
@endif
@endsection
