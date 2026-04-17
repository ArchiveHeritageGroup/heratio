{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plansailingisystems
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Records Transfers')

@section('content')
@php
    $statusColors = ['proposed' => 'secondary', 'scheduled' => 'info', 'in_transit' => 'warning', 'received' => 'primary', 'accessioned' => 'success', 'rejected' => 'danger', 'cancelled' => 'dark'];
    $currentStatus = $currentStatus ?? null;
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item active">Records Transfers</li>
                </ol>
            </nav>
            <h1><i class="fas fa-truck me-2"></i>Records Transfers</h1>
            <p class="text-muted">Transfers of records to the National Archives of Zimbabwe</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.transfer-create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Transfer
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ !$currentStatus ? 'active' : '' }}" href="{{ route('ahgnaz.transfers') }}">All</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'proposed' ? 'active' : '' }}" href="{{ route('ahgnaz.transfers', ['status' => 'proposed']) }}">Proposed</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'scheduled' ? 'active' : '' }}" href="{{ route('ahgnaz.transfers', ['status' => 'scheduled']) }}">Scheduled</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'in_transit' ? 'active' : '' }}" href="{{ route('ahgnaz.transfers', ['status' => 'in_transit']) }}">In Transit</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'received' ? 'active' : '' }}" href="{{ route('ahgnaz.transfers', ['status' => 'received']) }}">Received</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'accessioned' ? 'active' : '' }}" href="{{ route('ahgnaz.transfers', ['status' => 'accessioned']) }}">Accessioned</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($transfers->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-truck fa-3x mb-3"></i>
                    <p>No transfers found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Agency</th>
                            <th>Type</th>
                            <th>Proposed Date</th>
                            <th>Quantity</th>
                            <th>Restricted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfers as $transfer)
                            @php
                                $isOverdue = in_array($transfer->status, ['proposed', 'scheduled'])
                                    && $transfer->proposed_date
                                    && strtotime($transfer->proposed_date) < time();
                            @endphp
                            <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                <td>
                                    <a href="{{ route('ahgnaz.transfer-view', $transfer->id) }}">{{ $transfer->transfer_number }}</a>
                                </td>
                                <td>{{ $transfer->transferring_agency }}</td>
                                <td>{{ ucfirst($transfer->transfer_type ?? '') }}</td>
                                <td>
                                    {{ $transfer->proposed_date ?? '-' }}
                                    @if ($isOverdue)
                                        <span class="badge bg-danger">OVERDUE</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($transfer->quantity_linear_metres)
                                        {{ number_format($transfer->quantity_linear_metres, 2) }}m
                                    @endif
                                    @if ($transfer->quantity_boxes)
                                        / {{ $transfer->quantity_boxes }} boxes
                                    @endif
                                </td>
                                <td>
                                    @if ($transfer->contains_restricted)
                                        <span class="badge bg-danger"><i class="fas fa-lock"></i> Yes</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$transfer->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgnaz.transfer-view', $transfer->id) }}" class="btn btn-sm atom-btn-outline-primary">
                                        <i class="fas fa-eye"></i>
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
