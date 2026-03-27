@extends('theme::layouts.1col')

@section('title', __('Add donor'))

@section('content')
  <h1>{{ __('Add donor') }}</h1>
  <div class="alert alert-info">
    {{ __('Use the donor management module to add a new donor.') }}
  </div>
  <a href="{{ route('donor.browse') }}" class="btn atom-btn-outline-light">{{ __('Browse donors') }}</a>
@endsection
