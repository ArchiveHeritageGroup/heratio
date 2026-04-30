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

@section('title', 'Views Report')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item active">Views Report</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-eye me-2"></i>{{ __('Views Over Time') }}</h1>
        <a href="{{ route('statistics.export', ['type' => 'views', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-download me-1"></i>{{ __('Export CSV') }}
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('Period:') }}</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="start" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-auto">to</div>
                <div class="col-auto">
                    <input type="date" name="end" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-auto">
                    <select name="group" class="form-select form-select-sm">
                        <option value="day" {{ ($groupBy ?? 'day') === 'day' ? 'selected' : '' }}>{{ __('Daily') }}</option>
                        <option value="month" {{ ($groupBy ?? 'day') === 'month' ? 'selected' : '' }}>{{ __('Monthly') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Views Chart') }}</h5>
                </div>
                <div class="card-body">
                    <canvas id="viewsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Data Table') }}</h5>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>{{ __('Period') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th class="text-end">{{ __('Unique') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data ?? [] as $row)
                                <tr>
                                    <td>{{ $row->period }}</td>
                                    <td class="text-end">{{ number_format($row->total) }}</td>
                                    <td class="text-end">{{ number_format($row->unique_visitors) }}</td>
                                </tr>
                            @endforeach
                            @if(empty($data))
                                <tr><td colspan="3" class="text-center text-muted">No data</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewsData = @json($data ?? []);

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
