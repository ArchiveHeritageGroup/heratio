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

@section('title', 'Vendor Management')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2"><i class="fas fa-truck me-2"></i>Vendor Management</h1>
        <div>
            <a href="{{ route('ahgvendor.list') }}" class="btn btn-outline-secondary">
                <i class="fas fa-building me-1"></i>{{ __('All Vendors') }}
            </a>
            <a href="{{ route('ahgvendor.transactions') }}" class="btn btn-outline-secondary">
                <i class="fas fa-exchange-alt me-1"></i>{{ __('All Transactions') }}
            </a>
            <a href="{{ route('ahgvendor.add') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>{{ __('Add Vendor') }}
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">{{ __('Active Vendors') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['active_vendors'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">{{ __('Active Transactions') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['active_transactions'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-exchange-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1">{{ __('Pending Approval') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['pending_approval'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">{{ __('Overdue') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['overdue_items'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">{{ __('Items Out') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['items_out'] ?? 0) }}</h2>
                        </div>
                        <i class="fas fa-boxes fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">{{ __('This Month') }}</h6>
                            <h2 class="mb-0">R{{ number_format($stats['this_month_cost'] ?? 0, 2) }}</h2>
                        </div>
                        <i class="fas fa-coins fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Overdue Items Alert --}}
        @if (isset($overdueTransactions) && $overdueTransactions->count() > 0)
        <div class="col-12 mb-4">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue Transactions ({{ $overdueTransactions->count() }})
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Transaction #') }}</th>
                                    <th>{{ __('Vendor') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Expected Return') }}</th>
                                    <th>{{ __('Days Overdue') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($overdueTransactions as $trans)
                                <tr>
                                    <td>
                                        <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}">
                                            {{ e($trans->transaction_number) }}
                                        </a>
                                    </td>
                                    <td>{{ e($trans->vendor_name) }}</td>
                                    <td>{{ e($trans->service_name) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($trans->expected_return_date)->format('j M Y') }}</td>
                                    <td>
                                        <span class="badge bg-danger">
                                            {{ (new DateTime())->diff(new DateTime($trans->expected_return_date))->days }} days
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Recent Active Transactions --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i>{{ __('Recent Active Transactions') }}</span>
                    <a href="{{ route('ahgvendor.add-transaction') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>{{ __('New Transaction') }}
                    </a>
                </div>
                <div class="card-body p-0">
                    @if (isset($activeTransactions) && $activeTransactions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Transaction #') }}</th>
                                    <th>{{ __('Vendor') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Items') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Expected Return') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activeTransactions as $trans)
                                <tr>
                                    <td>
                                        <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}">
                                            {{ e($trans->transaction_number) }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('ahgvendor.view', ['slug' => $trans->vendor_slug]) }}">
                                            {{ e($trans->vendor_name) }}
                                        </a>
                                    </td>
                                    <td>{{ e($trans->service_name) }}</td>
                                    <td><span class="badge bg-secondary">{{ $trans->item_count ?? 0 }}</span></td>
                                    <td>@include('vendor::_statusBadge', ['status' => $trans->status])</td>
                                    <td>
                                        @if ($trans->expected_return_date)
                                            {{ \Carbon\Carbon::parse($trans->expected_return_date)->format('j M Y') }}
                                        @else
                                            <span class="text-muted">{{ __('Not set') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No active transactions</p>
                    </div>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('ahgvendor.transactions') }}">View all transactions &rarr;</a>
                </div>
            </div>
        </div>

        {{-- Status Distribution --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Transaction Status Distribution
                </div>
                <div class="card-body">
                    @php
                    $statusLabels = [
                        'pending_approval' => ['label' => 'Pending Approval', 'color' => 'warning'],
                        'approved' => ['label' => 'Approved', 'color' => 'info'],
                        'dispatched' => ['label' => 'Dispatched', 'color' => 'primary'],
                        'received_by_vendor' => ['label' => 'At Vendor', 'color' => 'secondary'],
                        'in_progress' => ['label' => 'In Progress', 'color' => 'info'],
                        'completed' => ['label' => 'Completed', 'color' => 'success'],
                        'ready_for_collection' => ['label' => 'Ready', 'color' => 'success'],
                        'returned' => ['label' => 'Returned', 'color' => 'dark'],
                        'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
                    ];
                    @endphp
                    @if (isset($statusCounts) && count($statusCounts) > 0)
                        @foreach ($statusCounts as $statusCount)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="badge bg-{{ $statusLabels[$statusCount->status]['color'] ?? 'secondary' }} me-2">
                                    {{ $statusCount->count }}
                                </span>
                                {{ $statusLabels[$statusCount->status]['label'] ?? ucfirst(str_replace('_', ' ', $statusCount->status)) }}
                            </span>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">No transactions yet</p>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('ahgvendor.add') }}" class="btn btn-outline-primary">
                            <i class="fas fa-building me-2"></i>{{ __('Add New Vendor') }}
                        </a>
                        <a href="{{ route('ahgvendor.add-transaction') }}" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>{{ __('Create Transaction') }}
                        </a>
                        <a href="{{ route('ahgvendor.transactions', ['overdue' => 1]) }}" class="btn btn-outline-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>{{ __('View Overdue Items') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
