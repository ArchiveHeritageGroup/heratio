@extends('theme::layouts.1col')
@section('title', 'Workflow Timeline')
@section('body-class', 'workflow timeline')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-stream me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Workflow Timeline</h1></div></div>
  <div class="timeline">
    @forelse($events ?? [] as $event)
    <div class="timeline-item d-flex mb-3">
      <div class="timeline-marker me-3"><div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:var(--ahg-primary);color:#fff"><i class="fas fa-{{ $event->icon ?? 'circle' }}"></i></div></div>
      <div class="timeline-content flex-grow-1"><div class="card"><div class="card-body py-2">
        <div class="d-flex justify-content-between"><strong>{{ $event->title ?? '' }}</strong><small class="text-muted">{{ $event->date ?? '' }}</small></div>
        <p class="small text-muted mb-0">{{ $event->description ?? '' }}</p>
      </div></div></div>
    </div>
    @empty<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No timeline events.</div>@endforelse
  </div>
@endsection
