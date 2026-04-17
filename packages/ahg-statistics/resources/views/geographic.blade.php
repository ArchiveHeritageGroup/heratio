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

@section('title', 'Geographic Distribution')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item active">Geographic Distribution</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-globe me-2"></i>Geographic Distribution</h1>
        <a href="{{ route('statistics.export', ['type' => 'geographic', 'start' => $startDate, 'end' => $endDate]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-download me-1"></i>Export CSV
        </a>
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

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px">#</th>
                            <th style="width: 80px">Code</th>
                            <th>Country</th>
                            <th class="text-end" style="width: 150px">Total Requests</th>
                            <th class="text-end" style="width: 150px">Unique Visitors</th>
                            <th style="width: 200px">Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $dataList = $data ?? [];
                            $maxTotal = !empty($dataList) ? max(array_map(fn($r) => $r->total, $dataList)) : 1;
                        @endphp
                        @foreach($dataList as $idx => $row)
                            @php $percent = round(($row->total / $maxTotal) * 100); @endphp
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td><span class="badge bg-secondary">{{ $row->country_code ?? '-' }}</span></td>
                                <td>{{ $row->country_name ?? 'Unknown' }}</td>
                                <td class="text-end"><strong>{{ number_format($row->total) }}</strong></td>
                                <td class="text-end">{{ number_format($row->unique_visitors) }}</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" style="width: {{ $percent }}%">{{ $percent }}%</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($dataList))
                            <tr><td colspan="6" class="text-center text-muted py-4">No geographic data for this period</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
