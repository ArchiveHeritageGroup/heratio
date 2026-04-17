{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plansailingisystems
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Closure Periods')

@section('content')
@php
    $statusColors = ['active' => 'success', 'expired' => 'warning', 'released' => 'info', 'extended' => 'secondary'];
    $typeColors   = ['standard' => 'primary', 'extended' => 'warning', 'indefinite' => 'danger', 'ministerial' => 'dark'];
    $currentStatus = $currentStatus ?? null;
    $currentType   = $currentType ?? null;
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item active">Closure Periods</li>
                </ol>
            </nav>
            <h1><i class="fas fa-lock me-2"></i>Closure Periods</h1>
            <p class="text-muted">Section 10 &mdash; 25-year closure period for restricted records</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.closure-create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Closure
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ !$currentStatus ? 'active' : '' }}" href="{{ route('ahgnaz.closures') }}">All</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'active' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', ['status' => 'active']) }}">Active</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'expired' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', ['status' => 'expired']) }}">Expired</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'released' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', ['status' => 'released']) }}">Released</a></li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ !$currentType ? 'active' : '' }}" href="{{ route('ahgnaz.closures', array_filter(['status' => $currentStatus])) }}">All Types</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentType === 'standard' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', array_filter(['status' => $currentStatus, 'closure_type' => 'standard'])) }}">Standard</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentType === 'extended' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', array_filter(['status' => $currentStatus, 'closure_type' => 'extended'])) }}">Extended</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentType === 'indefinite' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', array_filter(['status' => $currentStatus, 'closure_type' => 'indefinite'])) }}">Indefinite</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentType === 'ministerial' ? 'active' : '' }}" href="{{ route('ahgnaz.closures', array_filter(['status' => $currentStatus, 'closure_type' => 'ministerial'])) }}">Ministerial</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($closures->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-lock-open fa-3x mb-3"></i>
                    <p>No closure periods found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Years</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($closures as $closure)
                            @php
                                $isExpired = $closure->end_date && strtotime($closure->end_date) < time();
                            @endphp
                            <tr class="{{ $closure->status === 'active' && $isExpired ? 'table-warning' : '' }}">
                                <td>{{ $closure->io_title ?? ('Record #' . $closure->information_object_id) }}</td>
                                <td>
                                    <span class="badge bg-{{ $typeColors[$closure->closure_type] ?? 'secondary' }}">
                                        {{ ucfirst($closure->closure_type) }}
                                    </span>
                                </td>
                                <td>{{ $closure->start_date }}</td>
                                <td>
                                    @if ($closure->end_date)
                                        {{ $closure->end_date }}
                                        @if ($closure->status === 'active' && $isExpired)
                                            <span class="badge bg-danger">PAST DUE</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Indefinite</span>
                                    @endif
                                </td>
                                <td>{{ $closure->years ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$closure->status] ?? 'secondary' }}">
                                        {{ ucfirst($closure->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgnaz.closure-edit', $closure->id) }}" class="btn btn-sm atom-btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
