@extends('theme::layouts.1col')

@section('title', 'Registration Submitted')
@section('body-class', 'user register complete')

@section('content')

<h1><i class="fas fa-check-circle text-success me-2"></i>Registration Submitted</h1>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-envelope-open-text fa-5x text-primary mb-4"></i>
        <h2 class="mb-3">Thank You for Registering!</h2>
        <p class="lead text-muted mb-4">
          Your registration has been submitted successfully.
        </p>
        <div class="alert alert-info text-start">
          <h5><i class="fas fa-info-circle me-2"></i>What happens next?</h5>
          <ol class="mb-0">
            <li>Our staff will review your registration within 1-2 business days.</li>
            <li>You will receive an email once your account is approved.</li>
            <li>After approval, you can log in and book reading room visits.</li>
          </ol>
        </div>
        <div class="mt-4">
          <a href="{{ url('/') }}" class="btn btn-primary">
            <i class="fas fa-home me-2"></i>Return to Homepage
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
