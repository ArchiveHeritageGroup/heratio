{{--
  Federated GLAM network - public "Join the network" landing + request form
  (#1203 join-request slice).

  Explains the network (the network-effects story) and carries a moderated
  request form. Anonymous-readable and anonymous-submittable; the submission
  lands as 'pending' for an admin to review. Spam-resilience: server-side
  validation, a hidden honeypot field ("website"), and a render-time stamp for
  the minimum-dwell check. Full-width public layout. Never 500s.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layouts.1col')

@section('title', __('Join the GLAM network'))

@section('content')
<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">

            <div class="mb-4">
                <h4 class="mb-1">
                    <i class="bi bi-diagram-3 me-2"></i>{{ __('Join the federated GLAM network') }}
                </h4>
                <p class="text-muted mb-0">
                    {{ __('Galleries, libraries, archives and museums that join the network share their discovery records into a shared union catalogue. Every institution that joins makes the shared memory richer - and more discoverable - for everyone.') }}
                </p>
            </div>

            {{-- Network-effects framing with live headline totals. --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="display-6 fw-bold">{{ number_format($memberCount) }}</div>
                            <div class="text-muted">
                                {{ trans_choice('{0}institutions so far|{1}participating institution|[2,*]participating institutions', $memberCount) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="display-6 fw-bold">{{ number_format($recordCount) }}</div>
                            <div class="text-muted">{{ __('shared discovery records') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-stars me-1"></i>{{ __('Why join') }}
                    </h6>
                    <ul class="mb-0">
                        <li>{{ __('Your collections become discoverable alongside peer institutions in a single union catalogue.') }}</li>
                        <li>{{ __('You keep full control: sharing is opt-in, and you decide what is published.') }}</li>
                        <li>{{ __('The network grows in value for every member as more institutions join.') }}</li>
                        <li>{{ __('Open, standards-based metadata - no lock-in, works across borders and jurisdictions.') }}</li>
                    </ul>
                </div>
            </div>

            @if (session('error'))
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ __('Please correct the following:') }}
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-envelope-plus me-1"></i>{{ __('Request to join') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Tell us about your institution. Your request is reviewed by a network administrator before anything is published - nothing goes live automatically.') }}
                    </p>

                    <form method="POST" action="{{ route('federation.join.submit') }}" novalidate>
                        @csrf
                        <input type="hidden" name="rendered_at" value="{{ $renderedAt }}">

                        {{-- Honeypot: hidden from humans, tempting to bots. Real
                             users never see or fill this. Kept off-screen rather
                             than display:none-only so simple scrapers still hit it. --}}
                        <div aria-hidden="true"
                             style="position:absolute; left:-9999px; top:-9999px; height:0; overflow:hidden;">
                            <label for="website">{{ __('Website (leave this blank)') }}</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="institution_name" class="form-label">
                                {{ __('Institution name') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('institution_name') is-invalid @enderror"
                                   id="institution_name" name="institution_name" required maxlength="255"
                                   value="{{ old('institution_name') }}"
                                   placeholder="{{ __('e.g. National Library of Examplestan') }}">
                            @error('institution_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_name" class="form-label">{{ __('Contact name') }}</label>
                                <input type="text" class="form-control @error('contact_name') is-invalid @enderror"
                                       id="contact_name" name="contact_name" maxlength="255"
                                       value="{{ old('contact_name') }}">
                                @error('contact_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label">{{ __('Contact email') }}</label>
                                <input type="email" class="form-control @error('contact_email') is-invalid @enderror"
                                       id="contact_email" name="contact_email" maxlength="255"
                                       value="{{ old('contact_email') }}"
                                       placeholder="{{ __('name@institution.org') }}">
                                @error('contact_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="base_url" class="form-label">{{ __('Catalogue or website URL') }}</label>
                            <input type="url" class="form-control @error('base_url') is-invalid @enderror"
                                   id="base_url" name="base_url" maxlength="1024"
                                   value="{{ old('base_url') }}"
                                   placeholder="https://catalogue.institution.org">
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="what_they_share" class="form-label">{{ __('What would you share?') }}</label>
                            <textarea class="form-control @error('what_they_share') is-invalid @enderror"
                                      id="what_they_share" name="what_they_share" rows="4"
                                      placeholder="{{ __('Describe the collections you would contribute to the union catalogue.') }}">{{ old('what_they_share') }}</textarea>
                            @error('what_they_share')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">{{ __('Anything else? (optional)') }}</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>{{ __('Send join request') }}
                        </button>
                        <a href="{{ url('/federation/network') }}" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-diagram-3 me-1"></i>{{ __('See who has joined') }}
                        </a>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
