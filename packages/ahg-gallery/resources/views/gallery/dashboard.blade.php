@extends('theme::layouts.1col')
@section('title', 'Gallery Dashboard')
@section('body-class', 'gallery dashboard')
@section('title-block')
  <h1 class="mb-0"><i class="fas fa-palette me-2"></i>{{ __('Gallery Management') }}</h1>
  <span class="small text-muted">{{ __('Manage artwork and gallery items using CCO cataloguing standards') }}</span>
@endsection
@section('content')
@php
  $_total = (int)($totalItems ?? 0);
  $_withMedia = (int)($itemsWithMedia ?? 0);
  $_withoutMedia = max(0, $_total - $_withMedia);
  $_coverage = $_total > 0 ? round(($_withMedia / $_total) * 100) : 0;
@endphp

{{-- Statistics Row: 4 Heratio-native cards + 2 PSIS-only cards --}}
<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card bg-primary text-white"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('Total Items') }}</h6><h2 class="mb-0">{{ number_format($_total) }}</h2></div>
        <i class="fas fa-palette fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card bg-success text-white"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('With Media') }}</h6><h2 class="mb-0">{{ number_format($_withMedia) }}</h2></div>
        <i class="fas fa-image fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card bg-info text-white"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('Without Media') }}</h6><h2 class="mb-0">{{ number_format($_withoutMedia) }}</h2></div>
        <i class="fas fa-image fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card bg-warning text-dark"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('Media Coverage') }}</h6><h2 class="mb-0">{{ $_coverage }}%</h2></div>
        <i class="fas fa-chart-pie fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card bg-secondary text-white"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('Artists') }}</h6><h2 class="mb-0">{{ number_format($totalArtists ?? 0) }}</h2></div>
        <i class="fas fa-user fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card bg-dark text-white"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div><h6 class="card-title mb-0">{{ __('Active Loans') }}</h6><h2 class="mb-0">{{ number_format($activeLoans ?? 0) }}</h2></div>
        <i class="fas fa-exchange-alt fa-2x opacity-50"></i>
      </div>
    </div></div>
  </div>
</div>

<div class="row">
  {{-- Sidebar: Quick Actions (PSIS layout) + Heratio extras + About CCO --}}
  <div class="col-md-4">
    <div class="card mb-4">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
      </div>
      <div class="card-body">
        {{-- PSIS-cloned buttons --}}
        <a href="{{ route('gallery.create') }}" class="btn btn-primary w-100 mb-2">
          <i class="fas fa-plus me-2"></i>{{ __('Add new gallery item') }}
        </a>
        <a href="{{ route('gallery.browse') }}" class="btn btn-outline-primary w-100 mb-2">
          <i class="fas fa-list me-2"></i>{{ __('Browse all items') }}
        </a>
        <a href="{{ route('glam.search') }}?displayStandard=gallery" class="btn btn-outline-secondary w-100 mb-2">
          <i class="fas fa-search me-2"></i>{{ __('Advanced search') }}
        </a>
        <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-success w-100 mb-2">
          <i class="fas fa-chart-bar me-2"></i>{{ __('Gallery Reports') }}
        </a>
        {{-- Heratio-only buttons (kept) --}}
        <a href="{{ route('gallery.artists') }}" class="btn btn-outline-info w-100 mb-2">
          <i class="fas fa-user me-2"></i>{{ __('Artists') }}
        </a>
        <a href="{{ route('gallery.loans') }}" class="btn btn-outline-info w-100 mb-2">
          <i class="fas fa-exchange-alt me-2"></i>{{ __('Loans') }}
        </a>
        <a href="{{ route('gallery.valuations') }}" class="btn btn-outline-info w-100 mb-2">
          <i class="fas fa-coins me-2"></i>{{ __('Valuations') }}
        </a>
        <a href="{{ route('gallery.venues') }}" class="btn btn-outline-info w-100">
          <i class="fas fa-building me-2"></i>{{ __('Venues') }}
        </a>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About CCO') }}</h5>
      </div>
      <div class="card-body">
        <p class="small">Cataloguing Cultural Objects (CCO) is a standard for describing cultural works and their images.</p>
        <p class="small mb-0">Fields include: work type, materials, techniques, dimensions, subjects, and provenance.</p>
      </div>
    </div>
  </div>

  {{-- Recent Gallery Items (PSIS table enriched, Heratio columns preserved) --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Recent Gallery Items') }}</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Artist') }}</th>
                <th>{{ __('Date') }}</th>
                <th class="text-center">{{ __('Media') }}</th>
                <th>{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentItems ?? [] as $item)
                <tr>
                  <td><code>{{ $item->identifier ?? '' }}</code></td>
                  <td>
                    @if(!empty($item->slug))
                      <a href="{{ url('/gallery/' . $item->slug) }}">{{ $item->title ?? '[Untitled]' }}</a>
                    @else
                      {{ $item->title ?? '[Untitled]' }}
                    @endif
                  </td>
                  <td>{{ $item->creator_identity ?? '-' }}</td>
                  <td>{{ $item->created_at ?? '' }}</td>
                  <td class="text-center">
                    @if(!empty($item->digital_object_id))
                      <i class="fas fa-check-circle text-success"></i>
                    @else
                      <i class="fas fa-times-circle text-muted"></i>
                    @endif
                  </td>
                  <td>
                    @if(!empty($item->slug))
                      <a href="{{ url('/gallery/' . $item->slug . '/edit') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit"></i>
                      </a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No gallery items yet. Add your first item to get started.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
