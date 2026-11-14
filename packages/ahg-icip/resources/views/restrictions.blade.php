{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', __('ICIP Access Restrictions'))

@section('content')
@php
  $restrictionTypes = $restrictionTypes ?? [];
  $restrictions = collect($restrictions ?? []);
  $restrictionDescriptions = [
    'community_permission_required' => __('Written permission from the community is required before access.'),
    'gender_restricted_male' => __('Material may only be accessed by men who have appropriate cultural standing.'),
    'gender_restricted_female' => __('Material may only be accessed by women who have appropriate cultural standing.'),
    'initiated_only' => __('Material may only be accessed by initiated community members.'),
    'seasonal' => __('Access is restricted to certain times of year based on cultural protocols.'),
    'mourning_period' => __('Access is temporarily restricted during a period of mourning.'),
    'repatriation_pending' => __('Material is pending return to the community.'),
    'under_consultation' => __('Access decisions are pending community consultation.'),
    'elder_approval_required' => __('Approval from community Elders is required before access.'),
    'custom' => __('Custom restriction defined by the community.'),
  ];
@endphp
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">{{ __('Access Restrictions') }}</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-lock me-2"></i>{{ __('ICIP Access Restrictions') }}</h1>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">{{ __('ICIP tables have not been provisioned for this installation.') }}</div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      {{-- Active restrictions --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Active Restrictions') }}</h5>
        </div>
        <div class="card-body p-0">
          @if($restrictions->isEmpty())
            <div class="p-4 text-center text-muted">
              <i class="bi bi-lock fs-1"></i>
              <p class="mb-0 mt-2">{{ __('No restrictions applied') }}</p>
              <p class="small">{{ __('Restrictions can be applied from individual record ICIP pages') }}</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Restriction Type') }}</th>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Community') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Override') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($restrictions as $restriction)
                    <tr>
                      <td>
                        <i class="bi bi-lock-fill text-danger me-1"></i>
                        {{ $restrictionTypes[$restriction->restriction_type] ?? ucwords(str_replace('_', ' ', (string) $restriction->restriction_type)) }}
                        @if($restriction->restriction_type === 'custom' && !empty($restriction->custom_restriction_text))
                          <br><small class="text-muted">{{ $restriction->custom_restriction_text }}</small>
                        @endif
                      </td>
                      <td>
                        @if(!empty($restriction->slug))
                          <a href="{{ route('ahgicip.object-icip', ['slug' => $restriction->slug]) }}">{{ $restriction->object_title ?? __('Untitled') }}</a>
                        @else
                          {{ $restriction->object_title ?? __('Untitled') }}
                        @endif
                      </td>
                      <td>{{ $restriction->community_name ?? '-' }}</td>
                      <td>
                        @if(!empty($restriction->start_date) || !empty($restriction->end_date))
                          {{ !empty($restriction->start_date) ? \Carbon\Carbon::parse($restriction->start_date)->format('j M Y') : __('Start') }}
                          -
                          {{ !empty($restriction->end_date) ? \Carbon\Carbon::parse($restriction->end_date)->format('j M Y') : __('Indefinite') }}
                        @else
                          <span class="badge bg-secondary">{{ __('Indefinite') }}</span>
                        @endif
                      </td>
                      <td>
                        @if($restriction->override_security_clearance)
                          <span class="badge bg-danger" title="{{ __('Overrides standard security clearance') }}">
                            <i class="bi bi-shield-x"></i> {{ __('Yes') }}
                          </span>
                        @else
                          <span class="badge bg-secondary">{{ __('No') }}</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      {{-- Restriction types reference --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Restriction Types') }}</h5>
        </div>
        <div class="card-body">
          <dl class="mb-0">
            @foreach($restrictionTypes as $code => $label)
              <dt><i class="bi bi-lock text-danger me-1"></i>{{ $label }}</dt>
              <dd class="text-muted small mb-3">{{ $restrictionDescriptions[$code] ?? '' }}</dd>
            @endforeach
          </dl>
        </div>
      </div>

      {{-- Override info --}}
      <div class="card mt-4">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Security Override') }}</h5>
        </div>
        <div class="card-body small">
          <p>{{ __('When "Override Security Clearance" is enabled, ICIP restrictions take precedence over standard security clearance levels.') }}</p>
          <p class="mb-0">{{ __('This ensures that cultural protocols are respected even for users with high security clearance.') }}</p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
