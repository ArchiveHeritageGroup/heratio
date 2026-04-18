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

@section('title', 'Insurance Policies')

@section('content')
@php
    $policies = $policies ?? collect();
    $currentStatus = $currentStatus ?? '';
    $defaultCurrency = config('heratio.default_currency', '');
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">Insurance</li>
                </ol>
            </nav>
            <h1><i class="fas fa-shield-alt me-2"></i>Insurance Policies</h1>
            <p class="text-muted">Manage insurance coverage for heritage assets</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ 'active' === $currentStatus ? 'selected' : '' }}>Active</option>
                        <option value="expired" {{ 'expired' === $currentStatus ? 'selected' : '' }}>Expired</option>
                        <option value="cancelled" {{ 'cancelled' === $currentStatus ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Policies Table --}}
    <div class="card">
        <div class="card-body p-0">
            @if(empty($policies) || (is_object($policies) && method_exists($policies, 'isEmpty') && $policies->isEmpty()) || (is_countable($policies) && count($policies) === 0))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-shield-alt fa-3x mb-3"></i>
                    <p>No insurance policies found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Policy #</th>
                            <th>Provider</th>
                            <th>Coverage Type</th>
                            <th>Coverage Amount</th>
                            <th>Premium</th>
                            <th>Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($policies as $policy)
                            @php
                                $statusColors = ['active' => 'success', 'expired' => 'danger', 'cancelled' => 'secondary'];
                            @endphp
                            <tr>
                                <td>{{ $policy->policy_number ?? '-' }}</td>
                                <td>{{ $policy->provider_name ?? '-' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $policy->coverage_type ?? '-')) }}</td>
                                <td>
                                    {{ $policy->coverage_currency ?? $defaultCurrency }}
                                    {{ number_format($policy->coverage_amount ?? 0, 2) }}
                                </td>
                                <td>
                                    {{ $policy->premium_currency ?? $defaultCurrency }}
                                    {{ number_format($policy->premium_amount ?? 0, 2) }}
                                </td>
                                <td>
                                    {{ !empty($policy->coverage_start) ? \Carbon\Carbon::parse($policy->coverage_start)->format('j M Y') : '-' }} -
                                    {{ !empty($policy->coverage_end) ? \Carbon\Carbon::parse($policy->coverage_end)->format('j M Y') : '-' }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$policy->status ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst($policy->status ?? 'Unknown') }}
                                    </span>
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
