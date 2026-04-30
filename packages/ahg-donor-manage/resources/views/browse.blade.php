{{--
  Donor browse - Heratio

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with Heratio. If not, see <https://www.gnu.org/licenses/>.
--}}
@extends('theme::layouts.1col')

@section('title', 'Donors')
@section('body-class', 'browse donor')

@php
  $total = method_exists($pager, 'getNbResults') ? $pager->getNbResults() : (method_exists($pager, 'count') ? $pager->count() : count($pager->getResults()));
  $activeSort = request('sort', 'alphabetic');
  $activeDir = request('sortDir', $activeSort === 'lastUpdated' ? 'desc' : 'asc');
  $q = request('subquery', '');
@endphp

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('donor.browse') }}">Donors</a></li>
          <li class="breadcrumb-item active">Browse</li>
        </ol>
      </nav>
      <h1 class="h3 mb-0">
        <i class="fas fa-hand-holding-heart text-primary me-2"></i>
        Browse donors
        <span class="badge bg-secondary ms-2">{{ number_format($total) }}</span>
      </h1>
    </div>
    @auth
      <a href="{{ route('donor.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New donor
      </a>
    @endauth
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('donor.browse') }}">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="donor-search">{{ __('Search') }}</label>
            <input type="text" id="donor-search" name="subquery" class="form-control" placeholder="{{ __('Donor name, identifier...') }}" value="{{ $q }}">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="donor-sort">{{ __('Sort by') }}</label>
            <select name="sort" id="donor-sort" class="form-select">
              @foreach($sortOptions as $key => $label)
                <option value="{{ $key }}" @selected($activeSort === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="donor-dir">{{ __('Direction') }}</label>
            <select name="sortDir" id="donor-dir" class="form-select">
              <option value="asc" @selected($activeDir === 'asc')>{{ __('Ascending') }}</option>
              <option value="desc" @selected($activeDir === 'desc')>{{ __('Descending') }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="donor-limit">{{ __('Per page') }}</label>
            <select name="limit" id="donor-limit" class="form-select">
              @foreach([10, 25, 50, 100] as $n)
                <option value="{{ $n }}" @selected((int) request('limit', 10) === $n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Results --}}
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Identifier') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Updated') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pager->getResults() as $doc)
              <tr>
                <td>
                  <a href="{{ route('donor.show', $doc['slug']) }}" class="fw-bold">
                    {{ $doc['name'] ?: '[Untitled]' }}
                  </a>
                </td>
                <td><small>{{ $doc['identifier'] ?: '—' }}</small></td>
                <td>
                  <span class="badge bg-success">Active</span>
                </td>
                <td>
                  @if(!empty($doc['updated_at']))
                    {{ \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y') }}
                  @else
                    —
                  @endif
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('donor.show', $doc['slug']) }}" class="btn btn-outline-primary" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                    @auth
                      <a href="{{ route('donor.edit', $doc['slug']) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-edit"></i></a>
                    @endauth
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-5">
                  <i class="fas fa-hand-holding-heart fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">No donors found</p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3">
    @include('ahg-core::components.pager', ['pager' => $pager])
  </div>
</div>
@endsection
