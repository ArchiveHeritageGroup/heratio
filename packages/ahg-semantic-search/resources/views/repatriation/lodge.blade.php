{{--
  Repatriation engine - PUBLIC self-service claim lodging form for one traced
  object (heratio#1207). An origin community (or their representative) can lodge
  a repatriation claim DIRECTLY, with no staff account. The submission lands as a
  normal 'registered' claim with no staff author, flagged for review, and fires
  the staff notification. Respectful, neutral framing throughout: a claim is a
  documented request and its status, never a legal determination.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio. Distributed under the GNU AGPL v3 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Lodge a repatriation claim'))

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('repatriation.dashboard') }}">{{ __('Repatriation') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('displaced-heritage.index') }}">{{ __('Displaced-heritage register') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('Lodge a claim') }}</li>
                </ol>
            </nav>

            <h1 class="h4 mb-1">{{ __('Lodge a repatriation claim') }}</h1>
            <p class="text-muted">{{ __('You are lodging a claim about:') }} <strong>{{ $title }}</strong></p>

            {{-- Object context (read-only) --}}
            <div class="card border-0 bg-light mb-3">
                <div class="card-body py-2 px-3 small">
                    @if($currentHolder !== '')
                        <div><span class="text-muted">{{ __('Currently held by:') }}</span> {{ $currentHolder }}</div>
                    @endif
                    @if($originPlace !== '')
                        <div><span class="text-muted">{{ __('Recorded place of origin:') }}</span> {{ $originPlace }}</div>
                    @endif
                </div>
            </div>

            {{-- Standing neutral disclaimer --}}
            <div class="alert alert-secondary small" role="note">{{ $disclaimer }}</div>

            @if(session('error'))
                <div class="alert alert-warning small">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <p class="small text-muted">
                        {{ __('Tell us who you are and the grounds for your claim. A member of staff will review it and be in touch. Fields marked * are required.') }}
                    </p>

                    <form method="POST" action="{{ route('repatriation.lodge.submit', ['item' => $item]) }}">
                        @csrf
                        <input type="hidden" name="rendered_at" value="{{ $renderedAt }}">
                        {{-- Honeypot: hidden from people, tempting to bots. Leave blank. --}}
                        <div style="position:absolute;left:-9999px;" aria-hidden="true">
                            <label>{{ __('Website') }}<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <div class="mb-3">
                            <label for="rcl-community" class="form-label small">{{ __('Claimant community or nation') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('claimant_community') is-invalid @enderror" id="rcl-community" name="claimant_community" maxlength="512" required value="{{ old('claimant_community') }}">
                            @error('claimant_community')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rcl-name" class="form-label small">{{ __('Your name (optional)') }}</label>
                                <input type="text" class="form-control" id="rcl-name" name="claimant_name" maxlength="255" value="{{ old('claimant_name') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rcl-email" class="form-label small">{{ __('Contact email') }} <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="rcl-email" name="contact_email" maxlength="255" required value="{{ old('contact_email') }}">
                                @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('We use this only to confirm receipt and update you on the claim.') }}</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="rcl-origin" class="form-label small">{{ __('Place of origin (optional)') }}</label>
                            <input type="text" class="form-control" id="rcl-origin" name="origin_place" maxlength="512" value="{{ old('origin_place', $originPlace) }}">
                        </div>

                        <div class="mb-3">
                            <label for="rcl-evidence" class="form-label small">{{ __('Grounds for the claim and any evidence (optional)') }}</label>
                            <textarea class="form-control @error('evidence_summary') is-invalid @enderror" id="rcl-evidence" name="evidence_summary" rows="6" maxlength="20000" placeholder="{{ __('Describe the object\'s connection to your community and any supporting history or documentation. You do not need legal proof to start a dialogue.') }}">{{ old('evidence_summary') }}</textarea>
                            @error('evidence_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" value="1" id="rcl-consent" name="consent" {{ old('consent') ? 'checked' : '' }} required>
                            <label class="form-check-label small" for="rcl-consent">
                                {{ __('I understand this records a documented request to open a dialogue and is not a legal determination, and I consent to staff contacting me about it.') }}
                            </label>
                            @error('consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Lodge claim') }}</button>
                            <a href="{{ route('displaced-heritage.show', ['id' => $item]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
