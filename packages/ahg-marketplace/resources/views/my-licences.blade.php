{{--
  Buyer's licence agreements page. Receives: $licences (Collection).
--}}
@extends('theme::layouts.1col')

@section('title', __('My Licences') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace my-licences')

@section('content')

<h1 class="mb-4">
  <i class="fas fa-file-contract me-2 text-warning"></i> {{ __('My Licences') }}
</h1>

@if($licences->isEmpty())
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
      <h5>{{ __("You don't have any licence agreements yet") }}</h5>
      <p class="text-muted">{{ __('Buy a licence-type listing on the marketplace and your agreement will appear here.') }}</p>
      <a href="{{ url('/marketplace/browse?listing_type=licence') }}" class="btn btn-primary">
        <i class="fas fa-shopping-bag me-1"></i> {{ __('Browse licences') }}
      </a>
    </div>
  </div>
@else
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Agreement') }}</th>
          <th>{{ __('Listing') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Term') }}</th>
          <th>{{ __('Territory') }}</th>
          <th>{{ __('Status') }}</th>
          <th class="text-end" style="width:90px;">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($licences as $a)
          @php
            $statusBadge = match ($a->status) {
              'active'  => 'success',
              'expired' => 'secondary',
              'revoked' => 'danger',
              default   => 'secondary',
            };
            $expiresIn = $a->valid_until ? \Carbon\Carbon::parse($a->valid_until)->diffForHumans() : null;
          @endphp
          <tr>
            <td>
              <code class="small">{{ $a->agreement_number }}</code>
              <div class="small text-muted">{{ \Carbon\Carbon::parse($a->valid_from)->format('Y-m-d') }}</div>
            </td>
            <td>
              <a href="{{ url('/marketplace/listing?slug=' . $a->listing_slug) }}" class="text-decoration-none">
                {{ $a->listing_title }}
              </a>
              @if($a->seller_name)
                <div class="small text-muted">{{ __('from') }} {{ $a->seller_name }}</div>
              @endif
            </td>
            <td><span class="badge bg-info text-dark">{{ ucfirst(str_replace('_', ' ', $a->licence_type)) }}</span></td>
            <td class="small">
              @if($a->valid_until)
                {{ \Carbon\Carbon::parse($a->valid_until)->format('Y-m-d') }}
                <div class="text-muted">{{ $expiresIn }}</div>
              @else
                <i class="fas fa-infinity me-1 text-muted"></i>{{ __('Perpetual') }}
              @endif
            </td>
            <td class="small">{{ $a->territory }}</td>
            <td>
              <span class="badge bg-{{ $statusBadge }}">{{ $a->status }}</span>
              @if($a->exclusivity === 'exclusive')
                <span class="badge bg-warning text-dark ms-1">{{ __('exclusive') }}</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ url('/marketplace/listing?slug=' . $a->listing_slug) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View listing') }}">
                <i class="fas fa-external-link-alt"></i>
              </a>
            </td>
          </tr>
          {{-- Expanded detail row --}}
          <tr class="table-light">
            <td></td>
            <td colspan="6" class="small">
              <div class="d-flex flex-wrap gap-3">
                <span><strong>{{ __('Attribution') }}:</strong>
                  {!! $a->attribution_required ? '<i class="fas fa-check text-success"></i> Required' : '<i class="fas fa-times text-secondary"></i> Not required' !!}</span>
                <span><strong>{{ __('Modifications') }}:</strong>
                  {!! $a->modifications_allowed ? '<i class="fas fa-check text-success"></i> Allowed' : '<i class="fas fa-times text-secondary"></i> Not allowed' !!}</span>
                <span><strong>{{ __('Sub-licensing') }}:</strong>
                  {!! $a->sublicensing_allowed ? '<i class="fas fa-check text-success"></i> Allowed' : '<i class="fas fa-times text-secondary"></i> Not allowed' !!}</span>
                @if($a->max_copies)
                  <span><strong>{{ __('Max copies') }}:</strong> {{ number_format((int) $a->max_copies) }}</span>
                @endif
              </div>
              @if($a->scope)
                <div class="mt-1 text-muted">
                  <strong>{{ __('Scope') }}:</strong> {{ $a->scope }}
                </div>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<div class="mt-3">
  <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-outline-secondary">
    <i class="fas fa-receipt me-1"></i> {{ __('My purchases') }}
  </a>
  <a href="{{ url('/marketplace/browse') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> {{ __('Continue browsing') }}
  </a>
</div>

@endsection
