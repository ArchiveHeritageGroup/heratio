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
@php $formErrors = is_array($errors ?? null) ? $errors : []; $errors = new \Illuminate\Support\ViewErrorBag(); @endphp
@extends('theme::layouts.1col')

@section('title', 'Edit Transaction')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.transactions') }}">Transactions</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.view-transaction', ['id' => $transaction->id]) }}">{{ e($transaction->transaction_number) }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-edit me-2"></i>Edit Transaction: {{ e($transaction->transaction_number) }}
        </h1>
    </div>

    @if (!empty($formErrors))
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($formErrors as $field => $error)
            <li>{{ e(is_array($error) ? implode(', ', $error) : $error) }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="post" action="{{ route('ahgvendor.edit-transaction', ['id' => $transaction->id]) }}" class="needs-validation" novalidate>
        @csrf
        <input type="hidden" name="id" value="{{ $transaction->id }}">

        <div class="row">
            <div class="col-md-8">
                {{-- Basic Information --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Transaction Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vendor *</label>
                                <select name="vendor_id" class="form-select" required>
                                    <option value="">Select Vendor...</option>
                                    @foreach (($vendors ?? []) as $v)
                                    <option value="{{ $v->id }}" {{ ($transaction->vendor_id ?? '') == $v->id ? 'selected' : '' }}>
                                        {{ e($v->name) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Type *</label>
                                <select name="service_type_id" class="form-select" required>
                                    <option value="">Select Service...</option>
                                    @foreach (($serviceTypes ?? []) as $st)
                                    <option value="{{ $st->id }}" {{ ($transaction->service_type_id ?? '') == $st->id ? 'selected' : '' }}>
                                        {{ e($st->name) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Transaction Number</label>
                                <input type="text" name="transaction_number" class="form-control"
                                       value="{{ e($transaction->transaction_number ?? '') }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control"
                                       value="{{ e($transaction->reference_number ?? '') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (($statusOptions ?? []) as $code => $label)
                                    <option value="{{ $code }}" {{ ($transaction->status ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="low" {{ ($transaction->priority ?? '') === 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="normal" {{ ($transaction->priority ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                                    <option value="high" {{ ($transaction->priority ?? '') === 'high' ? 'selected' : '' }}>High</option>
                                    <option value="urgent" {{ ($transaction->priority ?? '') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ e($transaction->description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Dates --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar me-2"></i>Dates
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Request Date *</label>
                                <input type="date" name="request_date" class="form-control"
                                       value="{{ $transaction->request_date ?? date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control"
                                       value="{{ $transaction->due_date ?? '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Completion Date</label>
                                <input type="date" name="completion_date" class="form-control"
                                       value="{{ $transaction->completion_date ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Costs --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-dollar-sign me-2"></i>Costs
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estimated Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">R</span>
                                    <input type="number" name="estimated_cost" class="form-control" step="0.01"
                                           value="{{ $transaction->estimated_cost ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Actual Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">R</span>
                                    <input type="number" name="actual_cost" class="form-control" step="0.01"
                                           value="{{ $transaction->actual_cost ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="4">{{ e($transaction->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- Invoice Details --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Invoice Details
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control"
                                   value="{{ e($transaction->invoice_number ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control"
                                   value="{{ $transaction->invoice_date ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                @foreach (($paymentStatuses ?? []) as $code => $label)
                                <option value="{{ $code }}" {{ ($transaction->payment_status ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control"
                                   value="{{ $transaction->payment_date ?? '' }}">
                        </div>
                    </div>
                </div>

                {{-- Assignment --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i>Assignment
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Requested By</label>
                            <input type="text" name="requested_by" class="form-control"
                                   value="{{ e($transaction->requested_by ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned To</label>
                            <input type="text" name="assigned_to" class="form-control"
                                   value="{{ e($transaction->assigned_to ?? '') }}">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="{{ route('ahgvendor.view-transaction', ['id' => $transaction->id]) }}" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
</script>
@endsection
