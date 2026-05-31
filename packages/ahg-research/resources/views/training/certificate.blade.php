{{-- #1099 Training — completion certificate --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', __('Certificate'))

@section('content')
<div class="container py-4" style="max-width: 720px;">
  <div class="card border-success">
    <div class="card-body text-center p-5">
      <i class="fas fa-award text-success" style="font-size:3rem"></i>
      <h1 class="h4 mt-3">{{ __('Certificate of Completion') }}</h1>
      <p class="text-muted mb-4">{{ __('This certifies that') }}</p>
      <p class="h3">{{ $enrol['learner_name'] ?: ('Learner #'.$enrol['id']) }}</p>
      <p class="mb-4">{{ __('has successfully completed') }}<br><strong>{{ $course['title'] }}</strong></p>
      <div class="row text-start small text-muted justify-content-center">
        <div class="col-auto">{{ __('Score') }}: <strong>{{ $cert['score'] }}%</strong></div>
        <div class="col-auto">{{ __('Pass mark') }}: {{ $course['pass_mark'] }}%</div>
        <div class="col-auto">{{ __('Issued') }}: {{ \Illuminate\Support\Str::of((string)$cert['issued_at'])->substr(0,10) }}</div>
      </div>
      <p class="mt-4 mb-0"><span class="badge bg-success">{{ $cert['certificate_no'] }}</span></p>
    </div>
  </div>
  <p class="text-center mt-3">
    <a href="{{ route('research.training.learn', $enrol['id']) }}" class="btn atom-btn-white btn-sm">&larr; {{ __('Back to course') }}</a>
    <button onclick="window.print()" class="btn atom-btn-white btn-sm"><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
  </p>
</div>
@endsection
