{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plansailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Breach Register')

@section('content')
@php
    $currentStatus = request('status');
    $severityColors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
    $statusColors   = ['investigating' => 'warning', 'contained' => 'info', 'resolved' => 'success', 'ongoing' => 'danger'];
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item active">Breach Register</li>
                </ol>
            </nav>
            <h1><i class="fas fa-exclamation-triangle me-2"></i>Breach Register</h1>
            <p class="text-muted">Regulator notification required within the statutory window of discovery</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgcdpa.breach-create') }}" class="btn btn-danger">
                <i class="fas fa-plus me-1"></i> Report Breach
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ !$currentStatus ? 'active' : '' }}" href="{{ route('ahgcdpa.breaches') }}">All</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'investigating' ? 'active' : '' }}" href="{{ route('ahgcdpa.breaches', ['status' => 'investigating']) }}">Investigating</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'contained' ? 'active' : '' }}" href="{{ route('ahgcdpa.breaches', ['status' => 'contained']) }}">Contained</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'resolved' ? 'active' : '' }}" href="{{ route('ahgcdpa.breaches', ['status' => 'resolved']) }}">Resolved</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($breaches->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <p>No breaches recorded.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Incident Date</th>
                            <th>Severity</th>
                            <th>Regulator Notified</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($breaches as $breach)
                            <tr>
                                <td>
                                    <a href="{{ route('ahgcdpa.breach-view', ['id' => $breach->id]) }}">{{ $breach->reference_number }}</a>
                                </td>
                                <td>{{ ucfirst(str_replace('_', ' ', $breach->breach_type)) }}</td>
                                <td>{{ date('Y-m-d', strtotime($breach->incident_date)) }}</td>
                                <td>
                                    <span class="badge bg-{{ $severityColors[$breach->severity] ?? 'secondary' }}">
                                        {{ ucfirst($breach->severity) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($breach->potraz_notified)
                                        <span class="text-success"><i class="fas fa-check"></i>
                                            {{ $breach->potraz_notified_date ? date('Y-m-d', strtotime($breach->potraz_notified_date)) : '' }}
                                        </span>
                                    @else
                                        <span class="text-danger"><i class="fas fa-times"></i> Not Yet</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$breach->status] ?? 'secondary' }}">
                                        {{ ucfirst($breach->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgcdpa.breach-view', ['id' => $breach->id]) }}" class="btn btn-sm atom-btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (method_exists($breaches, 'links'))
                    <div class="p-3">{{ $breaches->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
