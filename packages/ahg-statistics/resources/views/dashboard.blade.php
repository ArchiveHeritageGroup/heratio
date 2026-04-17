{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Usage Statistics')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-chart-bar me-2"></i>Usage Statistics</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('statistics.export', ['type' => 'views', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-download me-1"></i>Export CSV
            </a>
            <a href="{{ route('statistics.admin') }}" class="btn btn-outline-primary">
                <i class="fas fa-cog me-1"></i>Settings
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-3 align-items-center">
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
                <div class="col-auto">
                    <a href="{{ route('statistics.dashboard', ['start' => \Carbon\Carbon::now()->subDays(7)->toDateString(), 'end' => \Carbon\Carbon::now()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">7 days</a>
                    <a href="{{ route('statistics.dashboard', ['start' => \Carbon\Carbon::now()->subDays(30)->toDateString(), 'end' => \Carbon\Carbon::now()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">30 days</a>
                    <a href="{{ route('statistics.dashboard', ['start' => \Carbon\Carbon::now()->subDays(90)->toDateString(), 'end' => \Carbon\Carbon::now()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">90 days</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Total Views</h6>
                            <h2 class="card-title mb-0">{{ number_format($stats['total_views'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-eye fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Downloads</h6>
                            <h2 class="card-title mb-0">{{ number_format($stats['total_downloads'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-download fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Unique Visitors</h6>
                            <h2 class="card-title mb-0">{{ number_format($stats['unique_visitors'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Countries</h6>
                            <h2 class="card-title mb-0">{{ number_format($stats['countries'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-globe fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Views Over Time</h5>
                    <a href="{{ route('statistics.views', ['start' => $startDate, 'end' => $endDate]) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                </div>
                <div class="card-body">
                    <canvas id="viewsChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Top Countries</h5>
                    <a href="{{ route('statistics.geographic', ['start' => $startDate, 'end' => $endDate]) }}" class="btn btn-sm btn-outline-secondary">All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($geoStats ?? [] as $geo)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $geo->country_name ?? $geo->country_code ?? 'Unknown' }}</span>
                                <span class="badge bg-primary rounded-pill">{{ number_format($geo->total) }}</span>
                            </div>
                        @endforeach
                        @if(empty($geoStats))
                            <div class="list-group-item text-muted text-center">No geographic data</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Viewed Items</h5>
                    <a href="{{ route('statistics.topItems', ['type' => 'view', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th class="text-end">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topItems ?? [] as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            @if(!empty($item->slug))
                                                <a href="{{ url('/'.$item->slug) }}" target="_blank">
                                                    {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 40) }}
                                                </a>
                                            @else
                                                {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 40) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($item->total) }}</td>
                                    </tr>
                                @endforeach
                                @if(empty($topItems))
                                    <tr><td colspan="3" class="text-center text-muted">No data</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-download me-2"></i>Top Downloads</h5>
                    <a href="{{ route('statistics.topItems', ['type' => 'download', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th class="text-end">Downloads</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topDownloads ?? [] as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            @if(!empty($item->slug))
                                                <a href="{{ url('/'.$item->slug) }}" target="_blank">
                                                    {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 40) }}
                                                </a>
                                            @else
                                                {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 40) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($item->total) }}</td>
                                    </tr>
                                @endforeach
                                @if(empty($topDownloads))
                                    <tr><td colspan="3" class="text-center text-muted">No data</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewsData = @json($viewsData ?? []);

    if (viewsData.length > 0) {
        const ctx = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: viewsData.map(d => d.period),
                datasets: [{
                    label: 'Total Views',
                    data: viewsData.map(d => d.total),
                    borderColor: 'rgb(13, 110, 253)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: 'Unique Visitors',
                    data: viewsData.map(d => d.unique_visitors),
                    borderColor: 'rgb(25, 135, 84)',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
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
