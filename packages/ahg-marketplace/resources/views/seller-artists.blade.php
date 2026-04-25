{{--
  Broker artist list — sellers managing multiple artists.
  Receives: $seller, $artists (Collection), $isBroker (bool).
--}}
@extends('theme::layouts.1col')

@section('title', __('My Artists'))
@section('body-class', 'marketplace seller-artists')

@section('content')

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">
      <i class="fas fa-palette me-2 text-primary"></i> {{ __('My Artists') }}
    </h1>
    <a href="{{ route('ahgmarketplace.seller-artist-create') }}" class="btn btn-success">
      <i class="fas fa-plus me-1"></i> {{ __('Add artist') }}
    </a>
  </div>

  @if(!$isBroker)
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i>
      Your seller type is <strong>{{ $seller->seller_type ?? 'collector' }}</strong>.
      Set it to <strong>broker</strong>, <strong>gallery</strong>, or <strong>dealer</strong> on
      <a href="{{ route('ahgmarketplace.seller-profile') }}">your profile</a>
      to enable broker features (artist selector + markup pricing on listings).
      Adding artists below is allowed regardless &mdash; you just won't see the broker fields on listing forms until your seller type is updated.
    </div>
  @endif

  @if($artists->isEmpty())
    <div class="card text-center py-5">
      <div class="card-body">
        <i class="fas fa-palette fa-3x text-muted mb-3"></i>
        <h5>{{ __("You don't represent any artists yet") }}</h5>
        <p class="text-muted">{{ __('Add artists you sell on behalf of, then assign them to your listings.') }}</p>
        <a href="{{ route('ahgmarketplace.seller-artist-create') }}" class="btn btn-primary">
          <i class="fas fa-plus me-1"></i> {{ __('Add your first artist') }}
        </a>
      </div>
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>{{ __('Artist') }}</th>
            <th>{{ __('Years') }}</th>
            <th>{{ __('Default markup') }}</th>
            <th>{{ __('Listings') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end" style="width:160px;">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($artists as $a)
            <tr>
              <td>
                <strong>{{ $a->display_name }}</strong>
                @if($a->nationality)
                  <span class="small text-muted ms-1">{{ $a->nationality }}</span>
                @endif
                @if($a->contact_email)
                  <div class="small text-muted">{{ $a->contact_email }}</div>
                @endif
              </td>
              <td class="small text-muted">
                {{ $a->birth_year ?: '?' }} &ndash; {{ $a->death_year ?: 'present' }}
              </td>
              <td class="small">
                @if($a->default_markup_type === 'percentage')
                  <span class="badge bg-info text-dark">{{ rtrim(rtrim(number_format($a->default_markup_value, 2), '0'), '.') }}%</span>
                @elseif($a->default_markup_type === 'fixed')
                  <span class="badge bg-info text-dark">+ {{ number_format($a->default_markup_value, 2) }}</span>
                @else
                  <span class="text-muted">none</span>
                @endif
                @if($a->default_commission_split)
                  <div class="text-muted">Split: {{ number_format($a->default_commission_split, 0) }}/{{ 100 - $a->default_commission_split }}</div>
                @endif
              </td>
              <td>{{ $a->total_listings }}</td>
              <td>
                <span class="badge bg-{{ $a->status === 'active' ? 'success' : 'secondary' }}">{{ $a->status }}</span>
              </td>
              <td class="text-end">
                <a href="{{ route('ahgmarketplace.seller-artist-edit', ['id' => $a->id]) }}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="{{ route('ahgmarketplace.seller-artist-delete') }}" class="d-inline"
                      onsubmit="return confirm('Remove {{ addslashes($a->display_name) }}? Listings linked to this artist will block deletion.');">
                  @csrf
                  <input type="hidden" name="id" value="{{ $a->id }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <div class="mt-4">
    <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Dashboard') }}
    </a>
  </div>

@endsection
