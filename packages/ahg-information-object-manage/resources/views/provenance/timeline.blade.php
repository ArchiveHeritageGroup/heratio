@extends('theme::layouts.1col')
@section('title', 'Provenance Timeline — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-project-diagram',
    'featureTitle' => 'Provenance Timeline',
    'featureDescription' => 'Visual timeline of ownership and custody changes',
  ])

  @if($events->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No provenance events to display.
    </div>
  @else
    <div class="timeline">
      @foreach($events as $event)
        <div class="card mb-3">
          <div class="card-body">
            <h6 class="card-title">{{ $event->event_date ?? 'Unknown date' }}</h6>
            <p class="card-text">{{ $event->description ?? '—' }}</p>
            <small class="text-muted">{{ $event->event_type ?? '' }} — {{ $event->agent_name ?? '' }}</small>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
