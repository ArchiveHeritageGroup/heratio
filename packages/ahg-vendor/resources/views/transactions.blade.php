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

@section('title', 'Vendor Transactions')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2"><i class="fas fa-exchange-alt me-2"></i>{{ __('Vendor Transactions') }}</h1>
        <div>
            <a href="{{ route('ahgvendor.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-tachometer-alt me-1"></i>{{ __('Dashboard') }}
            </a>
            <a href="{{ route('ahgvendor.add-transaction') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>{{ __('New Transaction') }}
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="{{ route('ahgvendor.transactions') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Transaction #...') }}" value="{{ e($filters['search'] ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach (($statusOptions ?? []) as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Vendor') }}</label>
                    <select name="vendor_id" class="form-select">
                        <option value="">{{ __('All Vendors') }}</option>
                        @foreach (($vendors ?? []) as $vendor)
                        <option value="{{ $vendor->id }}" {{ ($filters['vendor_id'] ?? '') == $vendor->id ? 'selected' : '' }}>{{ e($vendor->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Service') }}</label>
                    <select name="service_type_id" class="form-select">
                        <option value="">{{ __('All Services') }}</option>
                        @foreach (($serviceTypes ?? []) as $service)
                        <option value="{{ $service->id }}" {{ ($filters['service_type_id'] ?? '') == $service->id ? 'selected' : '' }}>{{ e($service->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('From') }}</label>
                    <input type="date" name="date_from" class="form-control" value="{{ e($filters['date_from'] ?? '') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('To') }}</label>
                    <input type="date" name="date_to" class="form-control" value="{{ e($filters['date_to'] ?? '') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="overdue" value="1" class="form-check-input" id="overdueOnly" {{ ($filters['overdue'] ?? '') ? 'checked' : '' }}>
                        <label class="form-check-label" for="overdueOnly">{{ __('Overdue') }}</label>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Results --}}
    <div class="card">
        <div class="card-header">
            <span class="badge bg-secondary me-2">{{ $transactions->count() }}</span> Transactions
        </div>
        <div class="card-body p-0">
            @if ($transactions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Transaction #') }}</th>
                            <th>{{ __('Vendor') }}</th>
                            <th>{{ __('Service') }}</th>
                            <th>{{ __('Items') }}</th>
                            <th>{{ __('Request Date') }}</th>
                            <th>{{ __('Expected Return') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Est. Cost') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $trans)
                        @php
                        $isOverdue = $trans->expected_return_date
                            && strtotime($trans->expected_return_date) < time()
                            && !$trans->actual_return_date
                            && !in_array($trans->status, ['returned', 'cancelled']);
                        @endphp
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                            <td>
                                <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}">
                                    <strong>{{ e($trans->transaction_number) }}</strong>
                                </a>
                                @if ($isOverdue)
                                <span class="badge bg-danger ms-1">{{ __('Overdue') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('ahgvendor.view', ['slug' => $trans->vendor_slug]) }}">
                                    {{ e($trans->vendor_name) }}
                                </a>
                            </td>
                            <td>{{ e($trans->service_name) }}</td>
                            <td><span class="badge bg-secondary">{{ $trans->item_count ?? 0 }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($trans->request_date)->format('j M Y') }}</td>
                            <td>
                                @if ($trans->expected_return_date)
                                    {{ \Carbon\Carbon::parse($trans->expected_return_date)->format('j M Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>@include('vendor::_statusBadge', ['status' => $trans->status])</td>
                            <td>
                                @if ($trans->actual_cost)
                                R{{ number_format($trans->actual_cost, 2) }}
                                @elseif ($trans->estimated_cost)
                                <span class="text-muted">~R{{ number_format($trans->estimated_cost, 2) }}</span>
                                @else
                                -
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}" class="btn btn-outline-primary" title="{{ __('View') }}">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('ahgvendor.edit-transaction', ['id' => $trans->id]) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-5 text-muted">
                <i class="fas fa-exchange-alt fa-3x mb-3"></i>
                <p>No transactions found</p>
                <a href="{{ route('ahgvendor.add-transaction') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>{{ __('Create First Transaction') }}
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
