@extends('theme::layouts.1col')
@section('title', 'Provenance — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-project-diagram',
    'featureTitle' => 'Provenance',
    'featureDescription' => 'Track chain of custody and ownership history',
  ])

  @if($events->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No provenance events recorded for this description.
    </div>
    <a href="#" class="btn atom-btn-outline-success" onclick="alert('Provenance event creation form — migration in progress'); return false;">
      <i class="fas fa-plus me-1"></i> Add provenance event
    </a>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>Event type</th>
            <th>Agent</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          @foreach($events as $event)
            <tr>
              <td>{{ $event->event_date ?? '—' }}</td>
              <td>{{ $event->event_type ?? '—' }}</td>
              <td>{{ $event->agent_name ?? '—' }}</td>
              <td>{{ $event->description ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
