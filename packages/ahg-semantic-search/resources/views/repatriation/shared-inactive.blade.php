{{--
  Repatriation shared record - inactive link (heratio#1207, pillar 3)

  Shown when a shared-record token is unknown, revoked or expired. Deliberately
  reveals nothing about whether a claim exists behind the token. HTTP 200, never
  a 404 or 500, so the surface never leaks. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Shared record link not active'))

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 text-center">
            <i class="fas fa-link-slash fa-3x text-muted mb-3"></i>
            <h1 class="h4 mb-2">{{ __('This shared-record link is not active') }}</h1>
            <p class="text-muted">
                {{ __('The link you used is not valid, or it has been revoked or has expired. If you were given this link by a holding institution, please contact them to request a new one.') }}
            </p>
        </div>
    </div>
</div>
@endsection
