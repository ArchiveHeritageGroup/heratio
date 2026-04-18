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

@section('title', 'Repository Statistics')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item active">Repository Statistics</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $repository->name ?? '' }}</h1>
            <span class="text-muted">Repository Statistics</span>
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
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Total Views</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_views'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Downloads</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_downloads'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Unique Visitors</h6>
                    <h2 class="mb-0">{{ number_format($stats['unique_visitors'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Items in Repository</h5>
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
                        @foreach($stats['top_items'] ?? [] as $idx => $item)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>
                                    <a href="{{ route('statistics.item', ['object_id' => $item->object_id, 'start' => $startDate, 'end' => $endDate]) }}">
                                        {{ $item->title ?? ('Object #'.$item->object_id) }}
                                    </a>
                                </td>
                                <td class="text-end">{{ number_format($item->total) }}</td>
                            </tr>
                        @endforeach
                        @if(empty($stats['top_items']))
                            <tr><td colspan="3" class="text-center text-muted py-4">No data for this period</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
