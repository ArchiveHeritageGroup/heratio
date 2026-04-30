{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Research Permits')

@section('content')
@php
    $statusColors = ['pending' => 'warning', 'approved' => 'info', 'active' => 'success', 'expired' => 'secondary', 'rejected' => 'danger', 'revoked' => 'dark'];
    $typeColors   = ['local' => 'success', 'foreign' => 'primary', 'institutional' => 'info'];
    $currentStatus = $currentStatus ?? null;
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item active">Research Permits</li>
                </ol>
            </nav>
            <h1><i class="fas fa-id-card me-2"></i>Research Permits</h1>
            <p class="text-muted">Foreign researchers: fee applies &mdash; Local researchers: free</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.permit-create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> {{ __('New Application') }}
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ !$currentStatus ? 'active' : '' }}" href="{{ route('ahgnaz.permits') }}">All</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'pending' ? 'active' : '' }}" href="{{ route('ahgnaz.permits', ['status' => 'pending']) }}">Pending</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'approved' ? 'active' : '' }}" href="{{ route('ahgnaz.permits', ['status' => 'approved']) }}">Approved</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'active' ? 'active' : '' }}" href="{{ route('ahgnaz.permits', ['status' => 'active']) }}">Active</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'expired' ? 'active' : '' }}" href="{{ route('ahgnaz.permits', ['status' => 'expired']) }}">Expired</a></li>
                <li class="nav-item"><a class="nav-link {{ $currentStatus === 'rejected' ? 'active' : '' }}" href="{{ route('ahgnaz.permits', ['status' => 'rejected']) }}">Rejected</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($permits->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-id-card fa-3x mb-3"></i>
                    <p>No permits found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Permit #') }}</th>
                            <th>{{ __('Researcher') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Research Topic') }}</th>
                            <th>{{ __('Valid Until') }}</th>
                            <th>{{ __('Fee') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permits as $permit)
                            @php
                                $isExpiring = $permit->status === 'active' && strtotime($permit->end_date) < strtotime('+30 days');
                                $topic = (string) ($permit->research_topic ?? '');
                                $typeKey = 'foreign';
                                $rname = trim(($permit->researcher_first ?? '') . ' ' . ($permit->researcher_last ?? ''));
                            @endphp
                            <tr class="{{ $isExpiring ? 'table-warning' : '' }}">
                                <td>
                                    <a href="{{ route('ahgnaz.permit-view', $permit->id) }}">{{ $permit->permit_number }}</a>
                                </td>
                                <td>
                                    {{ $rname !== '' ? $rname : ('Researcher #' . $permit->researcher_id) }}
                                    <br>
                                    <small class="badge bg-{{ $typeColors[$permit->permit_type] ?? 'secondary' }}">
                                        {{ ucfirst($permit->permit_type) }}
                                    </small>
                                </td>
                                <td>{{ ucfirst($permit->permit_type) }}</td>
                                <td>
                                    <span title="{{ $topic }}">
                                        {{ mb_strlen($topic) > 40 ? mb_substr($topic, 0, 40) . '...' : $topic }}
                                    </span>
                                </td>
                                <td>
                                    {{ $permit->end_date }}
                                    @if ($isExpiring)
                                        <span class="badge bg-warning text-dark">{{ __('Expiring') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($permit->fee_amount > 0)
                                        {{ $permit->fee_currency ?? 'USD' }} {{ number_format($permit->fee_amount, 2) }}
                                        @if ($permit->fee_paid)
                                            <i class="fas fa-check-circle text-success" title="{{ __('Paid') }}"></i>
                                        @else
                                            <i class="fas fa-times-circle text-danger" title="{{ __('Unpaid') }}"></i>
                                        @endif
                                    @else
                                        <span class="text-muted">{{ __('Free') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$permit->status] ?? 'secondary' }}">
                                        {{ ucfirst($permit->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgnaz.permit-view', $permit->id) }}" class="btn btn-sm atom-btn-outline-primary">
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
