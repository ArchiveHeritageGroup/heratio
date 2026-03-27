@extends('theme::layout')

@section('title', 'Provenance Timeline - ' . ($resource->title ?? $resource->slug))

@section('content')
<div class="container-fluid py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $resource->slug) }}">{{ $resource->title ?? $resource->slug }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('provenance.view', $resource->slug) }}">Provenance</a></li>
            <li class="breadcrumb-item active">Timeline</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Provenance Timeline</h4>
        <a href="{{ route('provenance.view', $resource->slug) }}" class="atom-btn-white">
            <i class="bi bi-arrow-left me-1"></i>Back to Provenance
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @if(isset($timeline) && count($timeline))
                <div class="position-relative" style="padding-left: 40px;">
                    <div class="position-absolute" style="left: 18px; top: 0; bottom: 0; width: 2px; background: var(--ahg-primary);"></div>
                    @foreach($timeline as $item)
                        <div class="mb-4 position-relative">
                            <div class="position-absolute" style="left: -30px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: var(--ahg-primary); border: 2px solid #fff;"></div>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1">{{ $item['title'] }}</h6>
                                        <small class="text-muted">{{ $item['date'] }}</small>
                                    </div>
                                    @if($item['agent'])
                                        <p class="mb-1"><small><strong>Agent:</strong> {{ $item['agent'] }}</small></p>
                                    @endif
                                    @if($item['description'])
                                        <p class="mb-0 text-muted">{{ $item['description'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted py-4">
                    <p>No timeline events to display.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
