@extends('theme::layouts.1col')
@section('title', 'Email Verification')
@section('body-class', 'heritage')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        @if($success ?? false)
        <i class="fas fa-check-circle display-1 text-success"></i>
        <h2 class="h4 mt-4">Email Verified!</h2>
        <p class="text-muted mb-4">Your email address has been verified successfully. You can now log in and start contributing to our heritage collection.</p>
        <a href="{{ route('heritage.contributor-login') }}" class="btn atom-btn-secondary btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a>
        @else
        <i class="fas fa-times-circle display-1 text-danger"></i>
        <h2 class="h4 mt-4">Verification Failed</h2>
        <p class="text-muted mb-4">{{ $error ?? 'The verification link is invalid or has expired.' }}</p>
        <a href="{{ route('heritage.contributor-register') }}" class="btn atom-btn-secondary"><i class="fas fa-user-plus me-2"></i>Register Again</a>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
