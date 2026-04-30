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

@section('title', 'Downloads Report')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item active">Downloads Report</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-download me-2"></i>Downloads Report</h1>
        <a href="{{ route('statistics.export', ['type' => 'downloads', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-download me-1"></i>Export CSV
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
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Downloads Over Time') }}</h5>
                </div>
                <div class="card-body">
                    <canvas id="downloadsChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Top Downloaded Items') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Title') }}</th>
                                    <th class="text-end">{{ __('Downloads') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($topDownloads ?? [], 0, 15) as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            @if(!empty($item->slug))
                                                <a href="{{ url('/'.$item->slug) }}" target="_blank">
                                                    {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 35) }}
                                                </a>
                                            @else
                                                {{ mb_substr($item->title ?? ('#'.$item->object_id), 0, 35) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($item->total) }}</td>
                                    </tr>
                                @endforeach
                                @if(empty($topDownloads))
                                    <tr><td colspan="3" class="text-center text-muted">No downloads recorded</td></tr>
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
    const downloadData = @json($data ?? []);

    if (downloadData.length > 0) {
        const ctx = document.getElementById('downloadsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: downloadData.map(d => d.period),
                datasets: [{
                    label: 'Downloads',
                    data: downloadData.map(d => d.total),
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgb(25, 135, 84)',
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
