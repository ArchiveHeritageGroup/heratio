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

@section('title', 'Heritage Asset Register')

@section('content')
@php
    $assets = $assets ?? collect();
    $categories = $categories ?? collect();
    $filters = $filters ?? [];
    $currentCategory = $filters['category_id'] ?? '';
    $currentStatus = $filters['status'] ?? '';
    $currentBasis = $filters['valuation_basis'] ?? '';
    $search = $filters['search'] ?? '';
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">Asset Register</li>
                </ol>
            </nav>
            <h1><i class="fas fa-archive me-2"></i>Heritage Asset Register</h1>
            <p class="text-muted">IPSAS-compliant asset inventory</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ipsas.asset.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Asset
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $currentCategory == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ 'active' === $currentStatus ? 'selected' : '' }}>Active</option>
                        <option value="on_loan" {{ 'on_loan' === $currentStatus ? 'selected' : '' }}>On Loan</option>
                        <option value="in_storage" {{ 'in_storage' === $currentStatus ? 'selected' : '' }}>In Storage</option>
                        <option value="disposed" {{ 'disposed' === $currentStatus ? 'selected' : '' }}>Disposed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="basis" class="form-select">
                        <option value="">All Valuation</option>
                        <option value="historical_cost" {{ 'historical_cost' === $currentBasis ? 'selected' : '' }}>Historical Cost</option>
                        <option value="fair_value" {{ 'fair_value' === $currentBasis ? 'selected' : '' }}>Fair Value</option>
                        <option value="nominal" {{ 'nominal' === $currentBasis ? 'selected' : '' }}>Nominal</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Search..." value="{{ $search }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Assets Table --}}
    <div class="card">
        <div class="card-body p-0">
            @if(empty($assets) || (is_object($assets) && method_exists($assets, 'isEmpty') && $assets->isEmpty()) || (is_countable($assets) && count($assets) === 0))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-archive fa-3x mb-3"></i>
                    <p>No assets found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Asset #</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Valuation Basis</th>
                            <th>Current Value</th>
                            <th>Status</th>
                            <th>Condition</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assets as $asset)
                            @php
                                $statusColors = [
                                    'active' => 'success',
                                    'on_loan' => 'info',
                                    'in_storage' => 'secondary',
                                    'under_conservation' => 'warning',
                                    'disposed' => 'danger',
                                    'lost' => 'dark',
                                ];
                                $conditionColors = [
                                    'excellent' => 'success',
                                    'good' => 'info',
                                    'fair' => 'warning',
                                    'poor' => 'danger',
                                    'critical' => 'dark',
                                ];
                                $basisColors = [
                                    'historical_cost' => 'primary',
                                    'fair_value' => 'success',
                                    'nominal' => 'warning',
                                    'not_recognized' => 'secondary',
                                ];
                                $defaultCurrency = config('heratio.default_currency', '');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('ipsas.asset.view', ['id' => $asset->id]) }}">
                                        {{ $asset->asset_number ?? '' }}
                                    </a>
                                </td>
                                <td>
                                    {{ substr($asset->title ?? '', 0, 50) }}{{ strlen($asset->title ?? '') > 50 ? '...' : '' }}
                                </td>
                                <td>{{ $asset->category_name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $basisColors[$asset->valuation_basis ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $asset->valuation_basis ?? '-')) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $asset->current_value_currency ?? $defaultCurrency }}
                                    {{ number_format($asset->current_value ?? 0, 2) }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$asset->status ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $asset->status ?? '-')) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $conditionColors[$asset->condition_rating ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst($asset->condition_rating ?? '-') }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ipsas.asset.view', ['id' => $asset->id]) }}"
                                       class="btn btn-sm btn-outline-primary">
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
