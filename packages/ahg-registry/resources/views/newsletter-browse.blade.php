{{--
  Registry — Newsletters
  Cloned from PSIS newsletterBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Newsletters'))
@section('body-class', 'registry registry-newsletter-browse')

@php
  $newsletters = $result ?? $newsletters ?? ['items' => collect(), 'total' => 0, 'page' => 1];
  $items = $newsletters['items'] ?? collect();
  $total = (int) ($newsletters['total'] ?? 0);
  $page  = (int) ($newsletters['page'] ?? 1);
  $limit = 12;
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Newsletters') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Newsletters') }}</h1>
  @if(Route::has('registry.newsletterSubscribe'))
    <a href="{{ route('registry.newsletterSubscribe') }}" class="btn btn-primary btn-sm">
      <i class="fas fa-envelope me-1"></i> {{ __('Subscribe') }}
    </a>
  @endif
</div>

@if(empty($items) || count($items) === 0)
  <div class="text-center py-5">
    <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
    <p class="text-muted">{{ __('No newsletters have been published yet.') }}</p>
    @if(Route::has('registry.newsletterSubscribe'))
      <a href="{{ route('registry.newsletterSubscribe') }}" class="btn btn-outline-primary mt-2">
        <i class="fas fa-envelope me-1"></i> {{ __('Subscribe to be notified') }}
      </a>
    @endif
  </div>
@else
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    @foreach($items as $nl)
      <div class="col">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Sent') }}</span>
              <small class="text-muted">{{ date('j M Y', strtotime($nl->sent_at ?? $nl->created_at ?? 'now')) }}</small>
            </div>
            <h5 class="card-title mb-2">
              @if(Route::has('registry.newsletterView'))
                <a href="{{ route('registry.newsletterView', ['id' => (int) $nl->id]) }}" class="text-decoration-none text-dark">{{ $nl->subject ?? '' }}</a>
              @else
                {{ $nl->subject ?? '' }}
              @endif
            </h5>
            @if(!empty($nl->excerpt))
              <p class="card-text text-muted small">{{ $nl->excerpt }}</p>
            @else
              <p class="card-text text-muted small">{{ \Illuminate\Support\Str::limit(strip_tags($nl->content ?? ''), 150) }}</p>
            @endif
          </div>
          <div class="card-footer bg-transparent border-top-0">
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted"><i class="fas fa-users me-1"></i>{{ number_format((int) ($nl->recipient_count ?? 0)) }} {{ __('recipients') }}</small>
              @if(Route::has('registry.newsletterView'))
                <a href="{{ route('registry.newsletterView', ['id' => (int) $nl->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Read') }} <i class="fas fa-arrow-right ms-1"></i></a>
              @endif
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($total > $limit)
    @php $totalPages = (int) ceil($total / $limit); @endphp
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        @for($p = 1; $p <= $totalPages; $p++)
          <li class="page-item {{ $p === $page ? 'active' : '' }}">
            <a class="page-link" href="{{ route('registry.newsletterBrowse', ['page' => $p]) }}">{{ $p }}</a>
          </li>
        @endfor
      </ul>
    </nav>
  @endif
@endif
@endsection
