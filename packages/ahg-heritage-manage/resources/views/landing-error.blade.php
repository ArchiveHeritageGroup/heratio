@extends('theme::layouts.1col')
@section('title', 'Heritage Portal')
@section('body-class', 'heritage')

@section('content')
<div class="container py-5">
  <div class="alert alert-warning">
    <h4>{{ __('Heritage Portal') }}</h4>
    <p>{{ $error ?? 'The heritage portal is not yet configured for this installation.' }}</p>
    <a href="{{ route('homepage') }}" class="btn atom-btn-secondary mt-2">Return to Homepage</a>
  </div>
</div>
@endsection
