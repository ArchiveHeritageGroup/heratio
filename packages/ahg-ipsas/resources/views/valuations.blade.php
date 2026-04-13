{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Asset Valuations')

@section('content')
@php
    $valuations = $valuations ?? collect();
    $filters = $filters ?? [];
    $currentType = $filters['type'] ?? '';
    $currentYear = $filters['year'] ?? date('Y');
    $defaultCurrency = config('heratio.default_currency', '');
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">Valuations</li>
                </ol>
            </nav>
            <h1><i class="fas fa-calculator me-2"></i>Asset Valuations</h1>
            <p class="text-muted">Track asset value changes for IPSAS compliance</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ipsas.valuation.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Valuation
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="initial" {{ 'initial' === $currentType ? 'selected' : '' }}>Initial</option>
                        <option value="revaluation" {{ 'revaluation' === $currentType ? 'selected' : '' }}>Revaluation</option>
                        <option value="impairment" {{ 'impairment' === $currentType ? 'selected' : '' }}>Impairment</option>
                        <option value="reversal" {{ 'reversal' === $currentType ? 'selected' : '' }}>Reversal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="year" class="form-control" placeholder="Year" value="{{ $currentYear }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Valuations Table --}}
    <div class="card">
        <div class="card-body p-0">
            @if(empty($valuations) || (is_object($valuations) && method_exists($valuations, 'isEmpty') && $valuations->isEmpty()) || (is_countable($valuations) && count($valuations) === 0))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-calculator fa-3x mb-3"></i>
                    <p>No valuations found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>Type</th>
                            <th>Basis</th>
                            <th>Previous Value</th>
                            <th>New Value</th>
                            <th>Change</th>
                            <th>Valuer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($valuations as $val)
                            @php
                                $change = ($val->new_value ?? 0) - ($val->previous_value ?? 0);
                                $typeColors = ['initial' => 'primary', 'revaluation' => 'success', 'impairment' => 'danger', 'reversal' => 'warning'];
                                $currency = $val->currency ?? $defaultCurrency;
                            @endphp
                            <tr>
                                <td>{{ !empty($val->valuation_date) ? \Carbon\Carbon::parse($val->valuation_date)->format('j M Y') : '-' }}</td>
                                <td>
                                    <a href="{{ route('ipsas.asset.view', ['id' => $val->asset_id ?? 0]) }}">
                                        {{ $val->asset_title ?? $val->asset_number ?? '#' . ($val->asset_id ?? '') }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $typeColors[$val->valuation_type ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst($val->valuation_type ?? '-') }}
                                    </span>
                                </td>
                                <td>{{ ucfirst(str_replace('_', ' ', $val->valuation_basis ?? '-')) }}</td>
                                <td>{{ $currency }} {{ number_format($val->previous_value ?? 0, 2) }}</td>
                                <td>{{ $currency }} {{ number_format($val->new_value ?? 0, 2) }}</td>
                                <td class="{{ $change >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 2) }}
                                </td>
                                <td>{{ $val->valuer_name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
