{{--
  Repatriation engine - confirmation after a PUBLIC self-service claim lodging
  (heratio#1207). Neutral, care-first acknowledgement.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio. Distributed under the GNU AGPL v3 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Claim received'))

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 text-center">
            <i class="bi bi-check-circle text-success" style="font-size:2.5rem;"></i>
            <h1 class="h4 mt-3">{{ __('Thank you - your claim has been received') }}</h1>
            <p class="text-muted">
                {{ __('Your repatriation claim has been recorded and is now awaiting review. If you gave a contact email, you will receive confirmation and an update whenever the claim\'s status changes.') }}
            </p>

            <div class="alert alert-secondary small text-start mt-4" role="note">{{ $disclaimer }}</div>

            <a href="{{ route('repatriation.dashboard') }}" class="btn btn-outline-secondary btn-sm mt-2">{{ __('Back to the repatriation overview') }}</a>
        </div>
    </div>
</div>
@endsection
