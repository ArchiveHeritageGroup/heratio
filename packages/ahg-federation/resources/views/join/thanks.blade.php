{{--
  Federated GLAM network - public "Join the network" confirmation page
  (#1203 join-request slice).

  Shown after a successful submission. Reassures the requester that their
  request was received and will be reviewed by an administrator before
  anything is published. Public layout. Never 500s.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layouts.1col')

@section('title', __('Request received'))

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card text-center">
                <div class="card-body p-5">
                    <div class="display-4 text-success mb-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h4 class="mb-3">{{ __('Thank you - your request has been received') }}</h4>
                    <p class="text-muted mb-4">
                        {{ __('A network administrator will review your request to join the federated GLAM network. We will be in touch using the contact details you provided. Nothing is published until your request is approved and your institution is added to the network.') }}
                    </p>
                    <a href="{{ url('/federation/network') }}" class="btn btn-primary">
                        <i class="bi bi-diagram-3 me-1"></i>{{ __('See the network directory') }}
                    </a>
                    <a href="{{ url('/') }}" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-house me-1"></i>{{ __('Back to home') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
