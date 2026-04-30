@extends('theme::layouts.1col')
@section('title', 'Publish Simulation')
@section('body-class', 'workflow simulate')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-play-circle me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Publish Readiness Simulation') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold"><i class="fas fa-play-circle me-2"></i>Gate Results</div><div class="card-body">
    @if(isset($gates) && count($gates))
      @foreach($gates as $gate)
      <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded {{ ($gate->passed ?? false) ? 'bg-light' : 'bg-danger bg-opacity-10' }}">
        <i class="fas fa-{{ ($gate->passed ?? false) ? 'check-circle text-success' : 'times-circle text-danger' }} fa-lg"></i>
        <div><strong>{{ $gate->name ?? '' }}</strong><br><small class="text-muted">{{ $gate->message ?? '' }}</small></div>
      </div>
      @endforeach
      <div class="mt-3"><span class="badge bg-{{ ($allPassed ?? false) ? 'success' : 'danger' }} fs-6">{{ ($allPassed ?? false) ? 'Ready to Publish' : 'Not Ready' }}</span></div>
    @else<p class="text-muted">No gates configured.</p>@endif
  </div></div>
@endsection
