@extends('theme::layouts.1col')
@section('title', '403 - Forbidden')
@section('content')
@php
  // Three different refusals used to share one "you must log in" page, which
  // was wrong for signed-in users and for records closed by a community's
  // cultural protocol (#1731) - signing in does not open those.
  $__msg = isset($exception) ? (string) $exception->getMessage() : '';
  $__cultural = $__msg !== '' && (str_starts_with($__msg, 'ICIP restriction') || str_contains($__msg, 'cultural protocol') || str_contains($__msg, 'Cultural notice'));
@endphp
<div class="text-center">
  <div id="content" class="d-inline-block mt-5 text-start" role="alert">
    <h1 class="h2 mb-0 p-3 border-bottom d-flex align-items-center">
      <i class="fas fa-fw fa-lg fa-lock me-3" aria-hidden="true"></i>
      @if($__cultural)
        {{ __('This record is restricted by a cultural protocol') }}
      @elseif(auth()->check())
        {{ __('Sorry, you do not have access to that page') }}
      @else
        {{ __('Sorry, you must log in to access that page') }}
      @endif
    </h1>

    <div class="p-3">
      <p>
        @if($__cultural)
          {{ __('The community connected to this record has asked that access to it be limited.') }}<br>
          <span class="text-muted small">{{ $__msg }}</span><br>
          {{ __('If you believe you should have access, please contact the institution.') }}
        @elseif(auth()->check())
          {{ __('Your account does not have permission to view it.') }}
        @else
          {{ __('The requested page requires authentication.') }}<br>
          {{ __('Please log in and try again.') }}
        @endif
      </p>

      <p class="mb-0">
        <a href="javascript:history.go(-1)">
          {{ __('Back to previous page.') }}
        </a><br>
        <a href="{{ url('/') }}">{{ __('Go to homepage.') }}</a>
      </p>
    </div>
  </div>
</div>
@endsection
