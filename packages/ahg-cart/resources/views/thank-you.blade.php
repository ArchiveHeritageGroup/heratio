@extends('theme::layouts.1col')
@section('title', 'Thank You')
@section('body-class', 'cart thank-you')

@section('content')
<div class="text-center py-5">
  <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
  <h1>Thank You</h1>
  <p class="lead text-muted">Your request has been submitted successfully.</p>
  <p>You will receive a confirmation email shortly with further details.</p>
  <a href="{{ url('/informationobject/browse') }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i>Continue browsing</a>
</div>
@endsection
