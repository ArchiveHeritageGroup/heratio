{{-- Registration Complete - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'profile'])
@endsection

@section('content')
  <div class="text-center py-5">
    <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
    <h1 class="mb-3">Registration Submitted</h1>
    <p class="lead text-muted">
      Your researcher registration has been successfully submitted and is now pending review.
    </p>
    <p class="text-muted">
      An administrator will review your application shortly. You will receive a notification once your registration has been approved.
    </p>
    <hr class="my-4">
    <a href="{{ route('research.dashboard') }}" class="btn btn-primary">
      <i class="fas fa-tachometer-alt me-1"></i>Return to Dashboard
    </a>
  </div>
@endsection
