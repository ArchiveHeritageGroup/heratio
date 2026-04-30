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

@section('title', $vendor->name ?? 'Vendor')

@section('content')
@php
$statsArr = is_object($stats ?? null) ? (array) $stats : ($stats ?? []);
@endphp

<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.list') }}">Vendors</a></li>
            <li class="breadcrumb-item active">{{ e($vendor->name) }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-building me-2"></i>{{ e($vendor->name) }}
            @php
            $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'danger', 'pending_approval' => 'warning'];
            @endphp
            <span class="badge bg-{{ $statusColors[$vendor->status] ?? 'secondary' }} ms-2">
                {{ ucfirst(str_replace('_', ' ', $vendor->status)) }}
            </span>
        </h1>
        <div>
            <a href="{{ route('ahgvendor.edit', ['slug' => $vendor->slug]) }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
            <a href="{{ route('ahgvendor.add-transaction', ['vendor' => $vendor->slug]) }}" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>{{ __('New Transaction') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            {{-- Vendor Details --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>{{ __('Vendor Details') }}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">{{ __('Vendor Code') }}</th>
                                    <td><code>{{ e($vendor->vendor_code) }}</code></td>
                                </tr>
                                <tr>
                                    <th>{{ __('Type') }}</th>
                                    <td>{{ ucfirst($vendor->vendor_type) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Registration #') }}</th>
                                    <td>{{ e($vendor->registration_number ?? '-') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('VAT Number') }}</th>
                                    <td>{{ e($vendor->vat_number ?? '-') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">{{ __('Phone') }}</th>
                                    <td>{{ e($vendor->phone ?? '-') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Alt. Phone') }}</th>
                                    <td>{{ e($vendor->phone_alt ?? '-') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Email') }}</th>
                                    <td>
                                        @if ($vendor->email)
                                        <a href="mailto:{{ $vendor->email }}">{{ e($vendor->email) }}</a>
                                        @else - @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('Website') }}</th>
                                    <td>
                                        @if ($vendor->website)
                                        <a href="{{ $vendor->website }}" target="_blank">{{ e($vendor->website) }}</a>
                                        @else - @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if ($vendor->street_address)
                    <div class="mt-3">
                        <strong>{{ __('Address:') }}</strong><br>
                        {!! nl2br(e($vendor->street_address)) !!}<br>
                        {{ e($vendor->city ?? '') }}@if (!empty($vendor->province)), {{ e($vendor->province) }}@endif
                        @if (!empty($vendor->postal_code)) {{ e($vendor->postal_code) }}@endif<br>
                        {{ e($vendor->country ?? '') }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Services --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tools me-2"></i>{{ __('Services Provided') }}
                </div>
                <div class="card-body">
                    @if ($services && (is_array($services) ? count($services) > 0 : $services->count() > 0))
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($services as $service)
                        <span class="badge bg-primary">{{ e(is_object($service) ? ($service->service_name ?? $service->name ?? '') : ($service['name'] ?? '')) }}</span>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted mb-0">No services assigned</p>
                    @endif
                </div>
            </div>

            {{-- Contacts --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users me-2"></i>{{ __('Contacts') }}</span>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addContactModal">
                        <i class="fas fa-plus me-1"></i>{{ __('Add Contact') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    @php
                    $contactCount = $contacts ? (is_array($contacts) ? count($contacts) : $contacts->count()) : 0;
                    @endphp
                    @if ($contactCount > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Position') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Primary') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($contacts as $contact)
                                <tr>
                                    <td><strong>{{ e($contact->name ?? $contact->contact_name ?? '') }}</strong></td>
                                    <td>{{ e($contact->position ?? '-') }}</td>
                                    <td>{{ e($contact->phone ?? $contact->mobile ?? '-') }}</td>
                                    <td>
                                        @if (!empty($contact->email))
                                        <a href="mailto:{{ $contact->email }}">{{ e($contact->email) }}</a>
                                        @else - @endif
                                    </td>
                                    <td>
                                        @if (!empty($contact->is_primary))
                                        <span class="badge bg-success">{{ __('Primary') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('ahgvendor.delete-contact', ['slug' => $vendor->slug, 'contactId' => $contact->id]) }}" class="d-inline" onsubmit="return confirm('Delete this contact?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p class="mb-0">No contacts added yet</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Recent Transactions --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-exchange-alt me-2"></i>{{ __('Recent Transactions') }}</span>
                    <a href="{{ route('ahgvendor.transactions', ['vendor_id' => $vendor->id]) }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @php
                    $transCount = $transactions ? (is_array($transactions) ? count($transactions) : $transactions->count()) : 0;
                    @endphp
                    @if ($transCount > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Transaction #') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $transItems = is_array($transactions) ? array_slice($transactions, 0, 5) : $transactions->take(5);
                                @endphp
                                @foreach ($transItems as $trans)
                                <tr>
                                    <td>
                                        <a href="{{ route('ahgvendor.view-transaction', ['id' => $trans->id]) }}">
                                            {{ e($trans->transaction_number) }}
                                        </a>
                                    </td>
                                    <td>{{ e($trans->service_name ?? '-') }}</td>
                                    <td>{{ $trans->request_date ? \Carbon\Carbon::parse($trans->request_date)->format('j M Y') : '-' }}</td>
                                    <td>
                                        @php
                                        $statusBadges = [
                                            'pending' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'cancelled' => 'secondary'
                                        ];
                                        $badgeClass = $statusBadges[$trans->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $trans->status)) }}</span>
                                    </td>
                                    <td>
                                        @if (!empty($trans->actual_cost))
                                        R{{ number_format($trans->actual_cost, 2) }}
                                        @elseif (!empty($trans->estimated_cost))
                                        <span class="text-muted">~R{{ number_format($trans->estimated_cost, 2) }}</span>
                                        @else - @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-exchange-alt fa-2x mb-2"></i>
                        <p class="mb-0">No transactions yet</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            {{-- Stats --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>{{ __('Statistics') }}
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h3 mb-0">{{ $statsArr['total_transactions'] ?? 0 }}</div>
                            <small class="text-muted">{{ __('Total Transactions') }}</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h3 mb-0">{{ $statsArr['active_transactions'] ?? 0 }}</div>
                            <small class="text-muted">{{ __('Active') }}</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 mb-0">R{{ number_format($statsArr['total_spent'] ?? 0, 0) }}</div>
                            <small class="text-muted">{{ __('Total Spent') }}</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 mb-0">{{ $statsArr['avg_rating'] ?? '-' }}</div>
                            <small class="text-muted">{{ __('Avg Rating') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Insurance --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shield-alt me-2"></i>{{ __('Insurance') }}
                </div>
                <div class="card-body">
                    @if (!empty($vendor->has_insurance))
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>{{ __('Provider') }}</th>
                            <td>{{ e($vendor->insurance_provider ?? '-') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Policy #') }}</th>
                            <td>{{ e($vendor->insurance_policy_number ?? '-') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Expiry') }}</th>
                            <td>
                                @if (!empty($vendor->insurance_expiry_date))
                                    @php $expired = strtotime($vendor->insurance_expiry_date) < time(); @endphp
                                    <span class="{{ $expired ? 'text-danger' : '' }}">
                                        {{ \Carbon\Carbon::parse($vendor->insurance_expiry_date)->format('j M Y') }}
                                        @if ($expired)<span class="badge bg-danger ms-1">{{ __('Expired') }}</span>@endif
                                    </span>
                                @else - @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Coverage') }}</th>
                            <td>
                                @if (!empty($vendor->insurance_coverage_amount))
                                R{{ number_format($vendor->insurance_coverage_amount, 2) }}
                                @else - @endif
                            </td>
                        </tr>
                    </table>
                    @else
                    <p class="text-muted mb-0"><i class="fas fa-exclamation-triangle text-warning me-1"></i>{{ __('No insurance on file') }}</p>
                    @endif
                </div>
            </div>

            {{-- Banking --}}
            @if (!empty($vendor->bank_name))
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-university me-2"></i>{{ __('Banking Details') }}
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>{{ __('Bank') }}</th>
                            <td>{{ e($vendor->bank_name) }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Branch') }}</th>
                            <td>{{ e($vendor->bank_branch ?? '-') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Account #') }}</th>
                            <td>{{ e($vendor->bank_account_number ?? '-') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Branch Code') }}</th>
                            <td>{{ e($vendor->bank_branch_code ?? '-') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            {{-- Notes --}}
            @if (!empty($vendor->notes))
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}
                </div>
                <div class="card-body">
                    {!! nl2br(e($vendor->notes)) !!}
                </div>
            </div>
            @endif

            {{-- Meta --}}
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info me-2"></i>{{ __('Record Info') }}
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        @if (!empty($vendor->created_at))
                        Created: {{ \Carbon\Carbon::parse($vendor->created_at)->format('j M Y H:i') }}<br>
                        @endif
                        @if (!empty($vendor->updated_at))
                        Updated: {{ \Carbon\Carbon::parse($vendor->updated_at)->format('j M Y H:i') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Contact Modal --}}
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgvendor.add-contact', ['slug' => $vendor->slug]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Add Contact') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Name *') }}</label>
                        <input type="text" name="contact_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Position') }}</label>
                            <input type="text" name="position" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Department') }}</label>
                            <input type="text" name="department" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" name="contact_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Mobile') }}</label>
                            <input type="text" name="mobile" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Email') }}</label>
                        <input type="email" name="contact_email" class="form-control">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_primary" value="1" class="form-check-input" id="isPrimary">
                        <label class="form-check-label" for="isPrimary">{{ __('Primary Contact') }}</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="contact_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add Contact') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
