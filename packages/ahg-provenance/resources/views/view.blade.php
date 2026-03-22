@extends('ahg-theme-b5::layout')

@section('title', 'Provenance - ' . ($resource->title ?? $resource->slug))

@section('content')
<div class="container-fluid py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $resource->slug) }}">{{ $resource->title ?? $resource->slug }}</a></li>
            <li class="breadcrumb-item active">Provenance</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Provenance</h4>
            <p class="text-muted mb-0">{{ $resource->title ?? $resource->slug }}</p>
        </div>
        <div>
            <a href="{{ route('provenance.timeline', $resource->slug) }}" class="atom-btn-white me-2">
                <i class="bi bi-bar-chart-steps me-1"></i>Timeline
            </a>
            <a href="{{ route('provenance.edit', $resource->slug) }}" class="atom-btn-white">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        </div>
    </div>

    @if($provenance && $provenance['events'] && $provenance['events']->count())
        @foreach($provenance['events'] as $event)
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        {{ $event->event_type ?? 'Event' }}
                        @if($event->event_date)
                            <small class="text-muted ms-2">{{ $event->event_date }}</small>
                        @endif
                    </h6>
                </div>
                <div class="card-body">
                    @if($event->agent_name ?? false)
                        <p><strong>Agent:</strong> {{ $event->agent_name }}</p>
                    @endif
                    @if($event->description ?? false)
                        <p>{{ $event->description }}</p>
                    @endif
                    @if($event->location ?? false)
                        <p><strong>Location:</strong> {{ $event->location }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-info">No provenance events recorded for this record.</div>
    @endif
</div>
@endsection
