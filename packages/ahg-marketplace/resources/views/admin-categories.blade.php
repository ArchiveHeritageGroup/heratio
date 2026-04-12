{{--
  Marketplace Admin — Categories

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminCategoriesSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Categories') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace categories')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Categories') }}</li>
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

<h1 class="h3 mb-4">{{ __('Manage Categories') }}</h1>

{{-- Add category form --}}
<div class="card mb-4">
  <div class="card-header"><h5 class="card-title mb-0">{{ __('Add Category') }}</h5></div>
  <div class="card-body">
    <form method="POST" action="{{ route('ahgmarketplace.admin-categories.post') }}" class="row g-2 align-items-end">
      @csrf
      <input type="hidden" name="form_action" value="create">
      <div class="col-md-2">
        <label class="form-label small">{{ __('Sector') }}</label>
        <select name="sector" class="form-select form-select-sm" required>
          <option value="">{{ __('Select...') }}</option>
          @foreach($sectors ?? [] as $sec)
            <option value="{{ $sec }}">{{ ucfirst($sec) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Name') }}</label>
        <input type="text" name="name" class="form-control form-control-sm" required placeholder="{{ __('Category name') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Description') }}</label>
        <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Optional description') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small">{{ __('Sort Order') }}</label>
        <input type="number" name="sort_order" class="form-control form-control-sm" value="0" min="0">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-plus me-1"></i> {{ __('Add') }}
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Categories grouped by sector --}}
@if(empty($categories) || count($categories) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-folder fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No categories yet') }}</h5>
      <p class="text-muted">{{ __('Add your first category above.') }}</p>
    </div>
  </div>
@else
  @php
    $grouped = [];
    foreach ($categories as $cat) {
      $sector = $cat->sector ?? 'other';
      $grouped[$sector][] = $cat;
    }
    ksort($grouped);
  @endphp
  @foreach($grouped as $sectorName => $sectorCats)
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <span class="badge bg-info me-2">{{ ucfirst($sectorName) }}</span>
          {{ __(':count categories', ['count' => count($sectorCats)]) }}
        </h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Slug') }}</th>
              <th>{{ __('Description') }}</th>
              <th class="text-end">{{ __('Sort Order') }}</th>
              <th>{{ __('Active') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sectorCats as $cat)
              <tr>
                <td class="fw-semibold">{{ $cat->name ?? '' }}</td>
                <td class="small text-muted">{{ $cat->slug ?? '' }}</td>
                <td class="small">{{ $cat->description ?? '-' }}</td>
                <td class="text-end small">{{ (int) ($cat->sort_order ?? 0) }}</td>
                <td>
                  @if($cat->is_active ?? 1)
                    <span class="badge bg-success">{{ __('Active') }}</span>
                  @else
                    <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                  @endif
                </td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCat{{ (int) $cat->id }}" title="{{ __('Edit') }}">
                    <i class="fas fa-edit"></i>
                  </button>
                  <form method="POST" action="{{ route('ahgmarketplace.admin-categories.post') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="category_id" value="{{ (int) $cat->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this category?') }}');">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Edit modals for this sector --}}
    @foreach($sectorCats as $cat)
      <div class="modal fade" id="editCat{{ (int) $cat->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST" action="{{ route('ahgmarketplace.admin-categories.post') }}">
              @csrf
              <input type="hidden" name="form_action" value="update">
              <input type="hidden" name="category_id" value="{{ (int) $cat->id }}">
              <div class="modal-header">
                <h5 class="modal-title">{{ __('Edit Category') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">{{ __('Sector') }}</label>
                  <select name="sector" class="form-select" required>
                    @foreach($sectors ?? [] as $sec)
                      <option value="{{ $sec }}" {{ ($cat->sector ?? '') === $sec ? 'selected' : '' }}>{{ ucfirst($sec) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('Name') }}</label>
                  <input type="text" name="name" class="form-control" value="{{ $cat->name ?? '' }}" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('Description') }}</label>
                  <input type="text" name="description" class="form-control" value="{{ $cat->description ?? '' }}">
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('Sort Order') }}</label>
                  <input type="number" name="sort_order" class="form-control" value="{{ (int) ($cat->sort_order ?? 0) }}" min="0">
                </div>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="is_active" value="1" id="active{{ (int) $cat->id }}" {{ ($cat->is_active ?? 1) ? 'checked' : '' }}>
                  <label class="form-check-label" for="active{{ (int) $cat->id }}">{{ __('Active') }}</label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  @endforeach
@endif
@endsection
