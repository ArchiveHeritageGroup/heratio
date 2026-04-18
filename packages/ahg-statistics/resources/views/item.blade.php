{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Item Statistics')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item active">Item Statistics</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $object->title ?? ('Object #'.$object->id) }}</h1>
            @if(!empty($object->identifier))
                <span class="text-muted">{{ $object->identifier }}</span>
            @endif
        </div>
        @if(!empty($object->slug))
            <a href="{{ url('/'.$object->slug) }}" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-external-link-alt me-1"></i>View Record
            </a>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-3 align-items-center">
                <input type="hidden" name="object_id" value="{{ $object->id }}">
                <div class="col-auto">
                    <label class="form-label mb-0">Period:</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="start" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-auto">to</div>
                <div class="col-auto">
                    <input type="date" name="end" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Total Views</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_views'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Unique Viewers</h6>
                    <h2 class="mb-0">{{ number_format($stats['unique_views'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Downloads</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_downloads'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Countries</h6>
                    <h2 class="mb-0">{{ count($stats['top_countries'] ?? []) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Views Over Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="viewsChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Top Countries</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($stats['top_countries'] ?? [] as $country)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $country->country_name ?? $country->country_code ?? 'Unknown' }}</span>
                                <span class="badge bg-primary rounded-pill">{{ number_format($country->count) }}</span>
                            </div>
                        @endforeach
                        @if(empty($stats['top_countries']))
                            <div class="list-group-item text-muted text-center">No geographic data</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewsData = @json($stats['views_by_day'] ?? []);

    if (viewsData.length > 0) {
        const ctx = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: viewsData.map(d => d.date),
                datasets: [{
                    label: 'Views',
                    data: viewsData.map(d => d.count),
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>
@endsection
