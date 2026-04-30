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

@section('title', 'Transaction')

@section('content')
@php
$statusColors = [
    'pending' => 'warning',
    'approved' => 'info',
    'in_progress' => 'primary',
    'completed' => 'success',
    'cancelled' => 'secondary',
    'on_hold' => 'dark'
];
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
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.transactions') }}">Transactions</a></li>
            <li class="breadcrumb-item active">{{ e($transaction->transaction_number) }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-file-invoice me-2"></i>{{ e($transaction->transaction_number) }}
            <span class="badge bg-{{ $statusColors[$transaction->status] ?? 'secondary' }} ms-2">
                {{ ucfirst(str_replace('_', ' ', $transaction->status)) }}
            </span>
        </h1>
        <div>
            <a href="{{ route('ahgvendor.edit-transaction', ['id' => $transaction->id]) }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="fas fa-sync me-1"></i>{{ __('Update Status') }}
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            {{-- Transaction Details --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Transaction Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th width="40%">{{ __('Transaction #') }}</th><td><code>{{ e($transaction->transaction_number) }}</code></td></tr>
                                <tr><th>{{ __('Vendor') }}</th><td><a href="{{ route('ahgvendor.view', ['slug' => $transaction->vendor_slug]) }}">{{ e($transaction->vendor_name) }}</a></td></tr>
                                <tr><th>{{ __('Service Type') }}</th><td>{{ e($transaction->service_name ?? '-') }}</td></tr>
                                <tr><th>{{ __('Priority') }}</th><td><span class="badge bg-{{ ['low'=>'success','normal'=>'primary','high'=>'warning','urgent'=>'danger'][$transaction->priority ?? 'normal'] ?? 'secondary' }}">{{ ucfirst($transaction->priority ?? 'normal') }}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th width="40%">{{ __('Request Date') }}</th><td>{{ $transaction->request_date ? \Carbon\Carbon::parse($transaction->request_date)->format('j M Y') : '-' }}</td></tr>
                                <tr><th>{{ __('Due Date') }}</th><td>{{ !empty($transaction->due_date) ? \Carbon\Carbon::parse($transaction->due_date)->format('j M Y') : '-' }}</td></tr>
                                <tr><th>{{ __('Completion Date') }}</th><td>{{ !empty($transaction->completion_date) ? \Carbon\Carbon::parse($transaction->completion_date)->format('j M Y') : '-' }}</td></tr>
                                <tr><th>{{ __('Reference') }}</th><td>{{ e($transaction->reference_number ?? '-') }}</td></tr>
                            </table>
                        </div>
                    </div>
                    @if (!empty($transaction->description))
                    <div class="mt-3"><strong>{{ __('Description:') }}</strong><p class="mb-0">{!! nl2br(e($transaction->description)) !!}</p></div>
                    @endif
                </div>
            </div>

            {{-- GLAM/DAM Items --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-archive me-2"></i>{{ __('GLAM/DAM Items') }}</span>
                </div>
                <div class="card-body p-0">
                    @php $itemCount = $items ? (is_array($items) ? count($items) : $items->count()) : 0; @endphp
                    @if ($itemCount > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>{{ __('Title') }}</th><th>{{ __('Identifier') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Cost') }}</th><th>{{ __('Status') }}</th><th width="80">{{ __('Actions') }}</th></tr>
                            </thead>
                            <tbody>
                                @php $total = 0; @endphp
                                @foreach ($items as $item)
                                @php $total += ($item->item_cost ?? $item->unit_cost ?? 0) * ($item->quantity ?? 1); @endphp
                                <tr>
                                    <td>
                                        @if (!empty($item->io_slug))
                                        <a href="{{ url('/' . $item->io_slug) }}" target="_blank"><i class="fas fa-external-link-alt fa-xs me-1"></i>{{ e($item->io_title ?? 'Untitled') }}</a>
                                        @else
                                        {{ e($item->description ?? $item->service_description ?? '-') }}
                                        @endif
                                    </td>
                                    <td><code>{{ e($item->identifier ?? '-') }}</code></td>
                                    <td><small>{{ e($item->service_notes ?? $item->notes ?? '-') }}</small></td>
                                    <td>{{ $item->quantity ?? 1 }}</td>
                                    <td>
                                        @php $cost = $item->item_cost ?? $item->unit_cost ?? null; @endphp
                                        {{ $cost ? 'R' . number_format($cost * ($item->quantity ?? 1), 2) : '-' }}
                                    </td>
                                    <td>
                                        @php $itemStatus = $item->service_completed ? 'completed' : 'pending'; @endphp
                                        <span class="badge bg-{{ ['pending'=>'warning','in_progress'=>'info','completed'=>'success'][$itemStatus] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $itemStatus)) }}</span>
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('ahgvendor.remove-transaction-item', ['transactionId' => $transaction->id, 'itemId' => $item->id]) }}" class="d-inline" onsubmit="return confirm('Remove this item?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-unlink"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light"><tr><th colspan="4" class="text-end">{{ __('Total:') }}</th><th><strong>R{{ number_format($total, 2) }}</strong></th><th colspan="2"></th></tr></tfoot>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4 text-muted"><i class="fas fa-archive fa-2x mb-2"></i><p class="mb-0">No items linked yet</p></div>
                    @endif
                </div>
            </div>

            @if (!empty($transaction->notes))
            <div class="card mb-4"><div class="card-header"><i class="fas fa-sticky-note me-2"></i>Notes</div><div class="card-body">{!! nl2br(e($transaction->notes)) !!}</div></div>
            @endif
        </div>

        <div class="col-md-4">
            {{-- Costs --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-dollar-sign me-2"></i>Cost Summary</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>{{ __('Estimated') }}</th><td class="text-end">{{ $transaction->estimated_cost ? 'R' . number_format($transaction->estimated_cost, 2) : '-' }}</td></tr>
                        <tr><th>{{ __('Actual') }}</th><td class="text-end"><strong>{{ $transaction->actual_cost ? 'R' . number_format($transaction->actual_cost, 2) : '-' }}</strong></td></tr>
                        <tr class="table-light"><th>{{ __('Items Total') }}</th><td class="text-end"><strong>R{{ number_format($total ?? 0, 2) }}</strong></td></tr>
                    </table>
                </div>
            </div>

            {{-- Invoice --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-file-invoice-dollar me-2"></i>Invoice</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>{{ __('Invoice #') }}</th><td>{{ e($transaction->invoice_number ?? '-') }}</td></tr>
                        <tr><th>{{ __('Invoice Date') }}</th><td>{{ !empty($transaction->invoice_date) ? \Carbon\Carbon::parse($transaction->invoice_date)->format('j M Y') : '-' }}</td></tr>
                        <tr><th>{{ __('Payment') }}</th><td><span class="badge bg-{{ ['pending'=>'warning','paid'=>'success','partial'=>'info','overdue'=>'danger'][$transaction->payment_status ?? 'pending'] ?? 'secondary' }}">{{ ucfirst($transaction->payment_status ?? 'pending') }}</span></td></tr>
                    </table>
                </div>
            </div>

            {{-- Record Info --}}
            <div class="card">
                <div class="card-header"><i class="fas fa-info me-2"></i>Record Info</div>
                <div class="card-body">
                    <small class="text-muted">
                        @if (!empty($transaction->created_at))
                        Created: {{ \Carbon\Carbon::parse($transaction->created_at)->format('j M Y H:i') }}<br>
                        @endif
                        @if (!empty($transaction->updated_at))
                        Updated: {{ \Carbon\Carbon::parse($transaction->updated_at)->format('j M Y H:i') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Status Update Modal --}}
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgvendor.update-transaction-status', ['id' => $transaction->id]) }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">{{ __('Update Status') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('New Status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach (($statusOptions ?? []) as $code => $label)
                            <option value="{{ $code }}" {{ $transaction->status === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="status_notes" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Update') }}</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
