{{--
  Marketplace Admin — Reviews moderation

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminReviewsSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Reviews') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace reviews')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Reviews') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Reviews') }}</h1>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="flagged" value="1" id="filterFlagged" {{ ($filters['flagged'] ?? '') ? 'checked' : '' }}>
          <label class="form-check-label" for="filterFlagged">{{ __('Flagged Only') }}</label>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Visibility') }}</label>
        <select name="is_visible" class="form-select form-select-sm">
          <option value="">{{ __('All') }}</option>
          <option value="1" {{ ($filters['is_visible'] ?? '') === '1' ? 'selected' : '' }}>{{ __('Visible') }}</option>
          <option value="0" {{ ($filters['is_visible'] ?? '') === '0' ? 'selected' : '' }}>{{ __('Hidden') }}</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
        </button>
      </div>
    </form>
  </div>
</div>

@if(empty($reviews) || count($reviews) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-star fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No reviews found') }}</h5>
      <p class="text-muted">{{ __('Try adjusting your filters.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">{{ __('ID') }}</th>
            <th>{{ __('Seller') }}</th>
            <th>{{ __('Reviewer') }}</th>
            <th>{{ __('Rating') }}</th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Comment') }}</th>
            <th>{{ __('Flagged') }}</th>
            <th>{{ __('Visible') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($reviews as $review)
            <tr class="{{ ($review->is_flagged ?? 0) ? 'table-warning' : '' }}">
              <td class="small text-muted">{{ (int) $review->id }}</td>
              <td class="small">{{ $review->seller_name ?? '-' }}</td>
              <td class="small">{{ $review->reviewer_name ?? '-' }}</td>
              <td class="text-nowrap">
                @for($s = 1; $s <= 5; $s++)
                  <i class="fa{{ $s <= (int) ($review->rating ?? 0) ? 's' : 'r' }} fa-star text-warning small"></i>
                @endfor
              </td>
              <td class="small">{{ $review->title ?? '-' }}</td>
              <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{ \Illuminate\Support\Str::limit($review->comment ?? '', 80) }}
              </td>
              <td>
                @if($review->is_flagged ?? 0)
                  <span class="badge bg-danger">{{ __('Flagged') }}</span>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                @if($review->is_visible ?? 1)
                  <span class="badge bg-success">{{ __('Visible') }}</span>
                @else
                  <span class="badge bg-secondary">{{ __('Hidden') }}</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <form method="POST" action="{{ route('ahgmarketplace.admin-reviews.post') }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="form_action" value="moderate">
                  <input type="hidden" name="review_id" value="{{ (int) $review->id }}">
                  @if($review->is_visible ?? 1)
                    <input type="hidden" name="is_visible" value="0">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Hide') }}">
                      <i class="fas fa-eye-slash"></i>
                    </button>
                  @else
                    <input type="hidden" name="is_visible" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Show') }}">
                      <i class="fas fa-eye"></i>
                    </button>
                  @endif
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / 30); @endphp
  @if($totalPages > 1)
    @php
      $query = http_build_query(array_filter([
        'flagged' => $filters['flagged'] ?? '',
        'is_visible' => $filters['is_visible'] ?? '',
      ]));
    @endphp
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?{{ $query }}&page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
