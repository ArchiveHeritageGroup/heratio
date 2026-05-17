@extends('theme::layouts.1col')
@section('title', __('Issue share link'))
@section('body-class', 'share-link issue-form')

@section('content')
@php
    $defaultExpiry = (new \DateTime('+' . (int) $defaultExpiryDays . ' days'))->format('Y-m-d');
    $maxExpiry     = (new \DateTime('+' . (int) $maxExpiryDays . ' days'))->format('Y-m-d');
    $minDate       = (new \DateTime('+1 day'))->format('Y-m-d');
@endphp

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
        @if($recordSlug)
            <li class="breadcrumb-item">
                <a href="{{ url('/' . $recordSlug) }}">{{ $recordTitle }}</a>
            </li>
        @else
            <li class="breadcrumb-item">{{ $recordTitle }}</li>
        @endif
        <li class="breadcrumb-item active">{{ __('Issue share link') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">
        <i class="fas fa-share-alt text-primary me-2"></i>{{ __('Issue share link') }}
    </h1>
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Cancel') }}
    </a>
</div>

<p class="text-muted">
    {{ __('Issuing a share link for') }}
    <strong>
        @if($recordSlug)
            <a href="{{ url('/' . $recordSlug) }}">{{ $recordTitle }}</a>
        @else
            {{ $recordTitle }}
        @endif
    </strong>
</p>

@if(!empty($errorMessage))
    <div class="alert alert-danger">{{ $errorMessage }}</div>
@endif

@if(!is_null($classificationLevel))
    <div class="alert alert-warning small">
        <i class="fas fa-shield-alt me-1"></i>
        {{ __('This record is classified (level') }} {{ (int) $classificationLevel }}).
        {{ __('Your clearance must meet or exceed this level to issue a link, and you need the') }}
        <code>share_link.create_classified</code> {{ __('permission.') }}
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('share-link.issue') }}" id="share-link-form">
            @csrf
            <input type="hidden" name="information_object_id" value="{{ (int) $informationObjectId }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="sl-expires-at" class="form-label">{{ __('Expires on') }}</label>
                    <input type="date" name="expires_at" id="sl-expires-at"
                           value="{{ $defaultExpiry }}"
                           min="{{ $minDate }}"
                           max="{{ $maxExpiry }}"
                           class="form-control" required>
                    <small class="text-muted">
                        {{ __('Default') }}: {{ (int) $defaultExpiryDays }} {{ __('days') }}.
                        {{ __('Max') }}: {{ (int) $maxExpiryDays }} {{ __('days') }}
                        {{ __('(longer requires the unlimited-expiry permission)') }}.
                    </small>
                </div>

                <div class="col-md-6">
                    <label for="sl-max-access" class="form-label">{{ __('Max views (optional)') }}</label>
                    <input type="number" name="max_access" id="sl-max-access" min="1" max="10000"
                           class="form-control" placeholder="{{ __('Unlimited within window') }}">
                    <small class="text-muted">{{ __('Leave blank for unlimited views until expiry.') }}</small>
                </div>

                <div class="col-md-6">
                    <label for="sl-email" class="form-label">{{ __('Recipient email (optional)') }}</label>
                    <input type="email" name="recipient_email" id="sl-email"
                           class="form-control" placeholder="researcher@example.com">
                    <small class="text-muted">{{ __('Informational only; the token itself grants access.') }}</small>
                </div>

                <div class="col-md-6">
                    <label for="sl-note" class="form-label">{{ __('Note (optional)') }}</label>
                    <input type="text" name="recipient_note" id="sl-note" maxlength="500"
                           class="form-control" placeholder="{{ __('Why are you sharing this?') }}">
                    <small class="text-muted">{{ __('Captured in the audit trail.') }}</small>
                </div>
            </div>

            <hr>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-link me-1"></i> {{ __('Issue link') }}
            </button>
            <a href="javascript:history.back()" class="btn btn-outline-secondary ms-1">{{ __('Cancel') }}</a>
        </form>
    </div>
</div>
@endsection
